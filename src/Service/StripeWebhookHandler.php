<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Event;
use Stripe\Invoice;
use Stripe\Subscription;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class StripeWebhookHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private SubscriptionMailerInterface $mailer,
        private ParameterBagInterface $params,
        private LoggerInterface $logger
    ) {}

    public function handle(Event $event): void
    {
        static $processedEvents = [];

        $this->logger->info('Stripe event received', [
            'id' => $event->id ?? null,
            'type' => $event->type ?? null,
        ]);

        if (isset($event->id) && in_array($event->id, $processedEvents, true)) {
            $this->logger->warning('Duplicate Stripe event ignored', [
                'id' => $event->id,
                'type' => $event->type ?? null,
            ]);

            return;
        }

        if (isset($event->id)) {
            $processedEvents[] = $event->id;
        }

        match ($event->type) {
            'invoice.payment_succeeded' => $this->handleInvoicePaid($event->data->object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            default => null,
        };
    }

    private function handleInvoicePaid(Invoice $invoice): void
    {
        if (!$invoice->customer || !$invoice->subscription) {
            return;
        }

        $user = $this->findUser((string) $invoice->customer);
        if (!$user) {
            return;
        }

        /** @var Subscription $subscription */
        $subscription = Subscription::retrieve($invoice->subscription);
        $periodEnd = $subscription->current_period_end ?? null;

        if (!$periodEnd) {
            return;
        }

        $wasInactive = !$user->isActive();

        if (
            !$wasInactive &&
            $user->getStripeSubscriptionId() === $subscription->id &&
            $user->getNextBillingDate()?->getTimestamp() === (int) $periodEnd
        ) {
            return;
        }

        $user->setStripeSubscriptionId($subscription->id);
        $user->activateSubscription(
            (new \DateTimeImmutable())->setTimestamp((int) $periodEnd)
        );

        $this->syncPlanFromSubscription($user, $subscription);

        if ($wasInactive) {
            $this->mailer->sendActivationEmail(
                $user->getEmail(),
                'Utilisateur',
                $user->getCurrentPlan()
            );
        }

        $this->em->flush();
    }

    private function handleSubscriptionUpdated(Subscription $sub): void
    {
        $user = $this->findUser((string) $sub->customer);
        if (!$user) {
            return;
        }

        if ($sub->cancel_at || $sub->cancel_at_period_end) {
            if ($user->isCancelAtPeriodEnd()) {
                return;
            }

            $endTimestamp = $sub->cancel_at ?? $sub->current_period_end;
            if (!$endTimestamp) {
                return;
            }

            $endDate = (new \DateTimeImmutable())->setTimestamp((int) $endTimestamp);
            $user->markCancellationAtPeriodEnd($endDate);

            $this->mailer->sendCancellationEmail(
                $user->getEmail(),
                'Utilisateur',
                $endDate
            );

            $this->em->flush();
            return;
        }

        if ($user->isCancelAtPeriodEnd()) {
            $user->activateSubscription(
                (new \DateTimeImmutable())->setTimestamp((int) $sub->current_period_end)
            );
        }

        $this->syncPlanFromSubscription($user, $sub);
        $this->em->flush();
    }

    private function handleSubscriptionDeleted(Subscription $sub): void
    {
        $user = $this->findUser((string) $sub->customer);

        if ($user) {
            $user->deactivateSubscription();
            $this->em->flush();
        }
    }

    private function findUser(string $stripeCustomerId): ?User
    {
        return $this->em
            ->getRepository(User::class)
            ->findOneBy(['stripeCustomerId' => $stripeCustomerId]);
    }

    private function syncPlanFromSubscription(User $user, Subscription $subscription): void
    {
        $priceId = $subscription->items->data[0]->price->id ?? null;
        if (!$priceId) {
            return;
        }

        if ($priceId === $this->params->get('stripe.price_yearly')) {
            $user->setCurrentPlan('yearly');
        } elseif ($priceId === $this->params->get('stripe.price_monthly')) {
            $user->setCurrentPlan('monthly');
        }
    }
}