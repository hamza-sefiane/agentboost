<?php

namespace App\Controller;

use App\Entity\User;
use Stripe\Stripe;
use Stripe\Subscription;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[IsGranted('ROLE_USER')]
final class CancelSubscriptionController extends AbstractController
{
    public function __construct(
        private ParameterBagInterface $params
    ) {}

    #[Route(
        '/subscription/cancel-cancellation',
        name: 'subscription_cancel_cancellation',
        methods: ['POST']
    )]
    public function __invoke(): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // 🔒 Sécurité
        if (
            !$user instanceof User ||
            !$user->getStripeSubscriptionId() ||
            !$user->isCancelAtPeriodEnd()
        ) {
            return $this->redirectToRoute('subscription_manage');
        }

        Stripe::setApiKey($this->params->get('stripe.secret_key'));

        // 🔁 Annule la résiliation programmée côté Stripe
        Subscription::update(
            $user->getStripeSubscriptionId(),
            [
                'cancel_at_period_end' => false,
            ]
        );

        /**
         * ⚠️ IMPORTANT
         * - On ne touche PAS la BD ici
         * - Le webhook remettra:
         *   - cancel_at_period_end = false
         *   - subscription_status = active
         */

        $this->addFlash(
            'success',
            'La résiliation a été annulée. Votre abonnement reste actif.'
        );

        return $this->redirectToRoute('subscription_manage');
    }
}
