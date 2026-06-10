<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Stripe\Subscription;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class CancelSubscriptionController extends AbstractController
{
    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
    ) {}

    #[Route('/subscription/cancel-cancellation', name: 'subscription_cancel_cancellation', methods: ['POST'])]
    public function __invoke(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('subscription_cancel_cancellation', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

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

        $this->sendCancellationCancelledEmail($user);

        $this->addFlash(
            'success',
            'La résiliation a été annulée. Votre abonnement reste actif.'
        );

        return $this->redirectToRoute('subscription_manage');
    }

    private function sendCancellationCancelledEmail(User $user): void
    {
        try {
            $this->mailer->send(
                (new TemplatedEmail())
                    ->from(new Address('contact@agentboost-immo.fr', 'AgentBoost'))
                    ->to((string) $user->getEmail())
                    ->subject('Résiliation annulée — AgentBoost')
                    ->htmlTemplate('emails/subscription_cancellation_cancelled.html.twig')
                    ->context([
                        'user' => $user,
                        'dashboardUrl' => $this->generateUrl(
                            'dashboard',
                            [],
                            UrlGeneratorInterface::ABSOLUTE_URL
                        ),
                        'manageSubscriptionUrl' => $this->generateUrl(
                            'subscription_manage',
                            [],
                            UrlGeneratorInterface::ABSOLUTE_URL
                        ),
                    ])
            );
        } catch (\Throwable) {
            // Ne bloque pas l’annulation si l’email échoue.
        }
    }
}
