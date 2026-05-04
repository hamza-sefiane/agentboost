<?php

namespace App\Service;

use App\Entity\StripeEvent;
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
        private readonly EntityManagerInterface $em,
        private readonly SubscriptionMailerInterface $mailer,
        private readonly ParameterBagInterface $params,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Event $event): void
    {
        $eventId = $event->id ?? null;

        $this->logger->info('Stripe event received', [
            'id' => $eventId,
            'type' => $event->type ?? null,
        ]);

        if ($eventId !== null) {
            $existing = $this->em
                ->getRepository(StripeEvent::class)
                ->findOneBy(['eventId' => $eventId]);

            if ($existing !== null) {
                $this->logger->warning('Duplicate Stripe event ignored', [
                    'id' => $eventId,
                ]);

                return;
            }

            $stripeEvent = new StripeEvent();
            $stripeEvent->setEventId($eventId);
            $stripeEvent->setPayload($event->toArray());

            $this->em->persist($stripeEvent);
        }

        match ($event->type) {
            'invoice.payment_succeeded' => $this->handleInvoicePaid($event->data->object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            default => null,
        };

        $this->em->flush();
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
        $subscription = Subscription::retrieve((string) $invoice->subscription);

        $periodEnd = $subscription->current_period_end ?? null;

        if (!$periodEnd) {
            return;
        }

        $wasInactive = !$user->isActive();

        if (
            !$wasInactive
            && $user->getStripeSubscriptionId() === $subscription->id
            && $user->getNextBillingDate()?->getTimestamp() === (int) $periodEnd
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
    }

    private function handleSubscriptionUpdated(Subscription $sub): void
    {
        $user = $this->findUser((string) $sub->customer);

        if (!$user) {
            return;
        }

        if ($sub->cancel_at || $sub->cancel_at_period_end) {
            $endTimestamp = $sub->cancel_at ?? $sub->current_period_end;

            if (!$endTimestamp) {
                return;
            }

            $endDate = (new \DateTimeImmutable())->setTimestamp((int) $endTimestamp);

            if (!$user->isCancelAtPeriodEnd()) {
                $user->markCancellationAtPeriodEnd($endDate);

                if (!$user->isDeleteAtPeriodEnd()) {
                    $this->mailer->sendCancellationEmail(
                        $user->getEmail(),
                        'Utilisateur',
                        $endDate
                    );
                }
            }

            return;
        }

        if ($user->isCancelAtPeriodEnd()) {
            $periodEnd = $sub->current_period_end ?? null;

            if ($periodEnd) {
                $user->activateSubscription(
                    (new \DateTimeImmutable())->setTimestamp((int) $periodEnd)
                );
            }
        }

        $this->syncPlanFromSubscription($user, $sub);
    }

    private function handleSubscriptionDeleted(Subscription $sub): void
    {
        $user = $this->findUser((string) $sub->customer);

        if (!$user) {
            return;
        }

        if ($user->isDeleteAtPeriodEnd()) {
            $this->em->remove($user);

            return;
        }

        $user->deactivateSubscription();
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
            $user->setCurrentPlan(User::PLAN_YEARLY);

            return;
        }

        if ($priceId === $this->params->get('stripe.price_monthly')) {
            $user->setCurrentPlan(User::PLAN_MONTHLY);
        }
    }
}