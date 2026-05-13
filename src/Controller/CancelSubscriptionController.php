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
final class CancelSubscriptionController extends AbstractController
{
    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
    ) {
    }

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
                    'idempotency_key' => 'cancel-cancellation-' . $user->getId() . '-' . time(),
                ]
            );
        } catch (ApiErrorException) {
            $this->addFlash(
                'error',
                'Impossible d’annuler la résiliation. Réessayez ou contactez le support.'
            );

            return $this->redirectToRoute('subscription_manage');
        }

        $user->activateSubscription($user->getNextBillingDate());

        $this->em->flush();

        try {
            $this->mailer->send(
                (new Email())
                    ->from('AgentBoost <no-reply@agentboost-immo.fr>')
                    ->to($user->getEmail())
                    ->subject('Résiliation annulée — AgentBoost')
                    ->html(
                        '<p>Bonjour,</p>
                        <p>Votre demande de résiliation a bien été annulée.</p>
                        <p>Votre abonnement AgentBoost reste actif et vos accès premium sont conservés.</p>
                        <p>— L’équipe AgentBoost</p>'
                    )
            );
        } catch (\Throwable) {
            // Ne bloque pas l’annulation si l’email échoue.
        }

        $this->addFlash(
            'success',
            'La résiliation a été annulée. Votre abonnement reste actif.'
        );

        return $this->redirectToRoute('subscription_manage');
    }
}