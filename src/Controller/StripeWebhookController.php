<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\SubscriptionMailer;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Invoice;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\Webhook;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
final class StripeWebhookController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SubscriptionMailer $mailer,
        private ParameterBagInterface $params
    ) {}

    public function __invoke(Request $request): Response
    {
        Stripe::setApiKey($this->params->get('stripe.secret_key'));

        // 🔐 Vérification signature Stripe
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->headers->get('stripe-signature'),
                $this->params->get('stripe.webhook_secret')
            );
        } catch (SignatureVerificationException|\UnexpectedValueException) {
            return new Response('Invalid signature', 400);
        }

        try {
            match ($event->type) {

                /**
                 * ✅ ACTIVATION / RENOUVELLEMENT
                 * 👉 SEULE SOURCE DE VÉRITÉ
                 */
                'invoice.payment_succeeded' =>
                    $this->handleInvoicePaid($event->data->object),

                /**
                 * 🔁 UPDATE SUBSCRIPTION
                 * - upgrade / downgrade
                 * - résiliation programmée
                 * - annulation de résiliation
                 */
                'customer.subscription.updated' =>
                    $this->handleSubscriptionUpdated($event->data->object),

                /**
                 * 🛑 FIN DÉFINITIVE
                 */
                'customer.subscription.deleted' =>
                    $this->handleSubscriptionDeleted($event->data->object),

                default => null,
            };
        } catch (ApiErrorException) {
            // Stripe retry
            return new Response('Stripe API error', 500);
        } catch (\Throwable) {
            // Erreur métier → idempotence
            return new Response('Ignored', 200);
        }

        return new Response('ok', 200);
    }

    // ======================================================
    // HANDLERS
    // ======================================================

    /**
     * 🔑 ACTIVATION / RENOUVELLEMENT
     */
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

        // 🔑 TRANSITION MÉTIER
        $wasInactive = !$user->isActive();

        // 🧠 Idempotence (renouvellement)
        if (
            !$wasInactive &&
            $user->getStripeSubscriptionId() === $subscription->id &&
            $user->getNextBillingDate()?->getTimestamp() === (int) $periodEnd
        ) {
            return;
        }

        // ✅ ÉCRITURE BD
        $user->setStripeSubscriptionId($subscription->id);
        $user->activateSubscription(
            (new \DateTimeImmutable())->setTimestamp((int) $periodEnd)
        );

        // 🔁 Sync plan
        $this->syncPlanFromSubscription($user, $subscription);

        // 📧 EMAIL ACTIVATION — UNE SEULE FOIS
        if ($wasInactive) {
            $this->mailer->sendActivationEmail(
                $user->getEmail(),
                'Utilisateur',
                $user->getCurrentPlan()
            );
        }

        $this->em->flush();
    }

    /**
     * 🔁 UPDATE SUBSCRIPTION
     */
    private function handleSubscriptionUpdated(Subscription $sub): void
    {
        $user = $this->findUser((string) $sub->customer);
        if (!$user) {
            return;
        }

        /**
         * ❌ RÉSILIATION PROGRAMMÉE
         */
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

            // 📧 EMAIL RÉSILIATION (UNE FOIS)
            $this->mailer->sendCancellationEmail(
                $user->getEmail(),
                'Utilisateur',
                $endDate
            );

            $this->em->flush();
            return;
        }

        /**
         * 🔄 ANNULATION DE RÉSILIATION
         */
        if ($user->isCancelAtPeriodEnd()) {
            $user->activateSubscription(
                (new \DateTimeImmutable())->setTimestamp((int) $sub->current_period_end)
            );
        }

        /**
         * 🔁 UPGRADE / DOWNGRADE
         */
        $this->syncPlanFromSubscription($user, $sub);
        $this->em->flush();
    }

    /**
     * 🛑 FIN DÉFINITIVE
     */
    private function handleSubscriptionDeleted(Subscription $sub): void
    {
        $user = $this->findUser((string) $sub->customer);

        if ($user) {
            $user->deactivateSubscription();
            $this->em->flush();
        }
    }

    // ======================================================
    // HELPERS
    // ======================================================

    private function findUser(string $stripeCustomerId): ?User
    {
        return $this->em->getRepository(User::class)
            ->findOneBy(['stripeCustomerId' => $stripeCustomerId]);
    }

    /**
     * 🔑 PLAN SYNC — SOURCE FIABLE = PRICE_ID
     */
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
