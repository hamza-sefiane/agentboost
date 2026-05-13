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
        private readonly EntityManagerInterface $em,
        private readonly ParameterBagInterface $params,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User || !$user->getStripeCustomerId()) {
            return new JsonResponse(['ok' => false], 400);
        }

        Stripe::setApiKey((string) $this->params->get('stripe.secret_key'));

        try {
            $subscriptions = Subscription::all([
                'customer' => $user->getStripeCustomerId(),
                'status' => 'active',
                'limit' => 1,
                'expand' => ['data.items.data.price'],
            ]);

            if (count($subscriptions->data) === 0) {
                return new JsonResponse(['ok' => false]);
            }

            $subscription = $subscriptions->data[0];

            $invoices = Invoice::all([
                'customer' => $user->getStripeCustomerId(),
                'subscription' => $subscription->id,
                'status' => 'paid',
                'limit' => 1,
            ]);

            if (count($invoices->data) === 0) {
                return new JsonResponse(['ok' => false]);
            }

            $invoice = $invoices->data[0];
            $periodEnd = $invoice->lines->data[0]->period->end ?? null;

            if (!$periodEnd) {
                return new JsonResponse(['ok' => false]);
            }

            $user->setStripeSubscriptionId($subscription->id);

            $price = $subscription->items->data[0]->price ?? null;
            $interval = $price?->recurring?->interval;

            if ($interval === 'year') {
                $user->setCurrentPlan('yearly');
            } else {
                $user->setCurrentPlan('monthly');
            }

            $user->activateSubscription(
                (new \DateTimeImmutable())->setTimestamp((int) $periodEnd)
            );

            $this->em->flush();

            return new JsonResponse([
                'ok' => true,
                'plan' => $user->getCurrentPlan(),
            ]);
        } catch (ApiErrorException) {
            return new JsonResponse(['ok' => false], 500);
        }
    }
}