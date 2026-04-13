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
final class ResiliationSubscriptionController extends AbstractController
{
    public function __construct(
        private ParameterBagInterface $params
    ) {}

    #[Route(
        '/subscription/cancel',
        name: 'subscription_cancel',
        methods: ['POST']
    )]
    public function __invoke(): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // 🔒 Sécurité + idempotence
        if (
            !$user instanceof User ||
            !$user->getStripeSubscriptionId() ||
            $user->isCancelAtPeriodEnd()
        ) {
            return $this->redirectToRoute('subscription_manage');
        }

        Stripe::setApiKey($this->params->get('stripe.secret_key'));

        // ❌ Résiliation programmée côté Stripe
        Subscription::update(
            $user->getStripeSubscriptionId(),
            [
                'cancel_at_period_end' => true,
            ]
        );

        /**
         * ⚠️ IMPORTANT
         * - AUCUNE écriture BD ici
         * - Le webhook :
         *   - passera subscription_status = grace
         *   - définira next_billing_date
         *   - enverra l’email
         */

        $this->addFlash(
            'success',
            'Votre abonnement sera résilié à la fin de la période en cours.'
        );

        return $this->redirectToRoute('subscription_manage');
    }
}
