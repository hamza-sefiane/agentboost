<?php

namespace App\Controller;

use App\Service\StripeWebhookHandler;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
final class StripeWebhookController
{
    public function __construct(
        private readonly StripeWebhookHandler $handler,
        #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        private readonly string $stripeSecretKey,
        #[Autowire('%env(STRIPE_WEBHOOK_SECRET)%')]
        private readonly string $stripeWebhookSecret,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        Stripe::setApiKey($this->stripeSecretKey);

        $signature = $request->headers->get('stripe-signature');

        if (!$signature) {
            return new Response('Missing Stripe signature', Response::HTTP_BAD_REQUEST);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $signature,
                $this->stripeWebhookSecret
            );

            $this->handler->handle($event);
        } catch (SignatureVerificationException|\UnexpectedValueException) {
            return new Response('Invalid Stripe signature', Response::HTTP_BAD_REQUEST);
        } catch (ApiErrorException) {
            return new Response('Stripe API error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new Response('ok', Response::HTTP_OK);
    }
}