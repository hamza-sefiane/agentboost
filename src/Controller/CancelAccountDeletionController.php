<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class CancelAccountDeletionController extends AbstractController
{
    #[Route('/account/delete/cancel', name: 'account_delete_cancel', methods: ['POST'])]
    public function __invoke(
        Request $request,
        EntityManagerInterface $em,
        Security $security,
        StripeClient $stripe,
        MailerInterface $mailer,
    ): RedirectResponse {
        $user = $security->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('cancel_account_deletion', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$user->isDeleteAtPeriodEnd()) {
            return $this->redirectToRoute('subscription_manage');
        }

        if ($user->getStripeSubscriptionId()) {
            try {
                $stripe->subscriptions->update(
                    $user->getStripeSubscriptionId(),
                    ['cancel_at_period_end' => false],
                    ['idempotency_key' => 'cancel-account-deletion-' . $user->getId() . '-' . time()]
                );
            } catch (ApiErrorException) {
                $this->addFlash(
                    'error',
                    'Impossible d’annuler la suppression du compte. Réessayez ou contactez le support.'
                );

                return $this->redirectToRoute('subscription_manage');
            }
        }

        $user->cancelDeletionAtPeriodEnd();

        if ($user->getNextBillingDate()) {
            $user->activateSubscription($user->getNextBillingDate());
        }

        $em->flush();

        $this->sendAccountDeletionCancelledEmail($mailer, $user);

        $this->addFlash(
            'success',
            'La suppression de votre compte a été annulée. Votre abonnement reste actif.'
        );

        return $this->redirectToRoute('subscription_manage');
    }

    private function sendAccountDeletionCancelledEmail(MailerInterface $mailer, User $user): void
    {
        try {
            $mailer->send(
                (new TemplatedEmail())
                    ->from(new Address('contact@agentboost-immo.fr', 'AgentBoost'))
                    ->to((string) $user->getEmail())
                    ->subject('Suppression de compte annulée — AgentBoost')
                    ->htmlTemplate('emails/account_deletion_cancelled.html.twig')
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
            // Ne bloque pas l’annulation de suppression si l’email échoue.
        }
    }
}
