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
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ResiliationSubscriptionController extends AbstractController
{
    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
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

            $subscription = Subscription::retrieve($user->getStripeSubscriptionId());

            $periodEnd = $subscription->current_period_end ?? null;

            if (!$periodEnd && $user->getNextBillingDate() instanceof \DateTimeImmutable) {
                $periodEnd = $user->getNextBillingDate()->getTimestamp();
            }

            if (!$periodEnd) {
                $periodEnd = (new \DateTimeImmutable('+1 month'))->getTimestamp();
            }

            $periodEndDate = (new \DateTimeImmutable())->setTimestamp((int) $periodEnd);

            $user->markCancellationAtPeriodEnd($periodEndDate);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            try {
                $this->mailer->send(
                    (new Email())
                        ->from('AgentBoost <no-reply@agentboost-immo.fr>')
                        ->to($user->getEmail())
                        ->subject('Résiliation de votre abonnement AgentBoost')
                        ->html(sprintf(
                            '<p>Bonjour,</p>
                            <p>Votre abonnement AgentBoost a bien été programmé pour résiliation.</p>
                            <p>Vous conservez l’accès à vos fonctionnalités jusqu’au <strong>%s</strong>.</p>
                            <p>Vous pouvez annuler cette résiliation depuis votre espace client tant que cette date n’est pas passée.</p>
                            <p>— L’équipe AgentBoost</p>',
                            $periodEndDate->format('d/m/Y')
                        ))
                );
            } catch (\Throwable) {
                // Ne bloque pas la résiliation si l’email échoue.
            }
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