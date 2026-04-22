<?php

namespace App\Controller;

use App\Service\StripeWebhookHandler;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
final class StripeWebhookController
{
    public function __construct(
        private StripeWebhookHandler $handler,
        private ParameterBagInterface $params
    ) {}

    public function __invoke(Request $request): Response
    {
        Stripe::setApiKey($this->params->get('stripe.secret_key'));

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
            $this->handler->handle($event);
        } catch (ApiErrorException) {
            return new Response('Stripe API error', 500);
        } catch (\Throwable) {
            return new Response('Ignored', 200);
        }

        return new Response('ok', 200);
    }
}