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
final class ResiliationSubscriptionController extends AbstractController
{
    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/subscription/cancel', name: 'subscription_cancel', methods: ['POST'])]
    public function __invoke(): RedirectResponse
    {
        $user = $this->getUser();

        if (
            !$user instanceof User ||
            !$user->getStripeSubscriptionId() ||
            $user->isCancelAtPeriodEnd() ||
            $user->isDeleteAtPeriodEnd()
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
                    'idempotency_key' => 'cancel-sub-' . $user->getId() . '-' . time(),
                ]
            );

            $subscription = Subscription::retrieve(
                $user->getStripeSubscriptionId()
            );

            $periodEnd = $subscription->current_period_end ?? null;

            if (!$periodEnd) {
                $this->addFlash('error', 'Impossible de récupérer la date de fin de période.');

                return $this->redirectToRoute('subscription_manage');
            }

            $user->markCancellationAtPeriodEnd(
                (new \DateTimeImmutable())->setTimestamp((int) $periodEnd)
            );

            $this->entityManager->persist($user);
            $this->entityManager->flush();

        } catch (ApiErrorException) {
            $this->addFlash('error', 'Impossible de résilier l’abonnement. Réessayez.');

            return $this->redirectToRoute('subscription_manage');
        }

        $this->addFlash(
            'success',
            'Votre abonnement sera résilié à la fin de la période en cours.'
        );

        return $this->redirectToRoute('subscription_manage');
    }
}