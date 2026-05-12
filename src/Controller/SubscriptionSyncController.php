<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\Invoice;
use Stripe\Stripe;
use Stripe\Subscription;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/subscription/sync', name: 'subscription_sync', methods: ['POST'])]
final class SubscriptionSyncController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ParameterBagInterface $params
    ) {}

    public function __invoke(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user || !$user->getStripeCustomerId()) {
            return new JsonResponse(['ok' => false], 400);
        }

        Stripe::setApiKey($this->params->get('stripe.secret_key'));

        try {
            // 1️⃣ Subscription active
            $subscriptions = Subscription::all([
                'customer' => $user->getStripeCustomerId(),
                'status'   => 'active',
                'limit'    => 1,
            ]);

            if (count($subscriptions->data) === 0) {
                return new JsonResponse(['ok' => false]);
            }

            $subscription = $subscriptions->data[0];

            // 2️⃣ Dernière invoice payée
            $invoices = Invoice::all([
                'customer'     => $user->getStripeCustomerId(),
                'subscription' => $subscription->id,
                'status'       => 'paid',
                'limit'        => 1,
            ]);

            if (count($invoices->data) === 0) {
                return new JsonResponse(['ok' => false]);
            }

            $invoice = $invoices->data[0];
            $periodEnd = $invoice->lines->data[0]->period->end ?? null;

            if (!$periodEnd) {
                return new JsonResponse(['ok' => false]);
            }

            // 3️⃣ ÉCRITURE BD (UNIQUE ENDROIT)
            $user->setStripeSubscriptionId($subscription->id);
            $user->activateSubscription(
                (new \DateTimeImmutable())->setTimestamp((int) $periodEnd)
            );

            $this->em->flush();

            return new JsonResponse(['ok' => true]);

        } catch (ApiErrorException) {
            return new JsonResponse(['ok' => false], 500);
        }
    }
}
