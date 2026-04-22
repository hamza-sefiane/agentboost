<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Event;
use Stripe\Invoice;
use Stripe\Subscription;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class StripeWebhookHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private SubscriptionMailerInterface $mailer,
        private ParameterBagInterface $params
    ) {}

    public function handle(Event $event): void
    {
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

        // Idempotence
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

        // Résiliation programmée
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

        // Annulation résiliation
        if ($user->isCancelAtPeriodEnd()) {
            $user->activateSubscription(
                (new \DateTimeImmutable())->setTimestamp((int) $sub->current_period_end)
            );
        }

        // Upgrade / downgrade
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