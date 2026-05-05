<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Stripe\Subscription;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class CancelSubscriptionController extends AbstractController
{
    public function __construct(
        private ParameterBagInterface $params,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/subscription/cancel-cancellation', name: 'subscription_cancel_cancellation', methods: ['POST'])]
    public function __invoke(): RedirectResponse
    {
        $user = $this->getUser();

        if (
            !$user instanceof User ||
            !$user->getStripeSubscriptionId() ||
            !$user->isCancelAtPeriodEnd() ||
            $user->isDeleteAtPeriodEnd() ||
            !$user->getNextBillingDate()
        ) {
            return $this->redirectToRoute('subscription_manage');
        }

        Stripe::setApiKey($this->params->get('stripe.secret_key'));

        try {
            Subscription::update(
                $user->getStripeSubscriptionId(),
                [
                    'cancel_at_period_end' => false,
                ],
                [
                    // 🔥 clé idempotente
                    'idempotency_key' => 'cancel-cancellation-'.$user->getId(),
                ]
            );
        } catch (ApiErrorException) {
            $this->addFlash(
                'error',
                'Impossible d’annuler la résiliation. Réessayez ou contactez le support.'
            );

            return $this->redirectToRoute('subscription_manage');
        }

        // 🔥 Update immédiat UX (mais Stripe reste source de vérité)
        $user->activateSubscription($user->getNextBillingDate());

        $this->em->flush();

        $this->addFlash(
            'success',
            'La résiliation a été annulée. Votre abonnement reste actif.'
        );

        return $this->redirectToRoute('subscription_manage');
    }
}