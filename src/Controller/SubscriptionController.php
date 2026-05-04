<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SubscriptionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    #[Route(
        '/subscribe/{plan}/checkout',
        name: 'create_checkout_session',
        requirements: ['plan' => 'monthly|yearly'],
        methods: ['POST']
    )]
    public function checkout(
        string $plan,
        #[Autowire('%env(STRIPE_PRICE_MONTHLY)%')] string $priceMonthly,
        #[Autowire('%env(STRIPE_PRICE_YEARLY)%')] string $priceYearly,
        #[Autowire('%env(STRIPE_SECRET_KEY)%')] string $stripeSecretKey
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }
        
        if (!$user->isVerified()) {
            return new JsonResponse(['error' => 'Email non vérifié'], 403);
        }

        $priceId = match ($plan) {
            'monthly' => $priceMonthly,
            'yearly' => $priceYearly,
            default => null,
        };

        if (!$priceId) {
            return new JsonResponse(['error' => 'Invalid plan'], 400);
        }

        Stripe::setApiKey($stripeSecretKey);

        // ✅ CRÉATION DU CUSTOMER STRIPE SI ABSENT
        if (!$user->getStripeCustomerId()) {
            $customer = Customer::create([
                'email' => $user->getEmail(),
                'metadata' => [
                    'user_id' => (string) $user->getId(),
                ],
            ]);

            $user->setStripeCustomerId($customer->id);
            $this->em->flush();
        }

        $session = Session::create([
            'mode' => 'subscription',
            'customer' => $user->getStripeCustomerId(),

            'subscription_data' => [
                'metadata' => [
                    'user_id' => (string) $user->getId(),
                ],
            ],

            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],

            'success_url' => $this->generateUrl(
                'subscription_processing',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'cancel_url' => $this->generateUrl(
                'pricing',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ]);

        return new JsonResponse(['id' => $session->id]);
    }
}
