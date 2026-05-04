<?php

namespace App\Controller;

use App\Entity\User;
use Stripe\Exception\ApiErrorException;
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

        if (
            !$user instanceof User ||
            !$user->getStripeSubscriptionId() ||
            $user->isCancelAtPeriodEnd()
        ) {
            return $this->redirectToRoute('subscription_manage');
        }

        Stripe::setApiKey($this->params->get('stripe.secret_key'));

        try {
            Subscription::update(
                $user->getStripeSubscriptionId(),
                [
                    'cancel_at_period_end' => true,
                ],
                [
                    // 🔥 clé idempotente (évite double exécution)
                    'idempotency_key' => 'cancel-sub-'.$user->getId(),
                ]
            );
        } catch (ApiErrorException) {
            $this->addFlash(
                'error',
                'Impossible de résilier l’abonnement. Réessayez.'
            );

            return $this->redirectToRoute('subscription_manage');
        }

        // ⚠️ Toujours laisser le webhook gérer la DB

        $this->addFlash(
            'success',
            'Votre abonnement sera résilié à la fin de la période en cours.'
        );

        return $this->redirectToRoute('subscription_manage');
    }
}