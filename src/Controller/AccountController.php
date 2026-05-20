<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AccountController extends AbstractController
{
    #[Route('/account/delete', name: 'account_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        EntityManagerInterface $em,
        Security $security,
        MailerInterface $mailer,
        #[Autowire('%env(STRIPE_SECRET_KEY)%')] string $stripeSecretKey,
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('delete_account', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($user->isActive()) {
            if ($user->getStripeSubscriptionId()) {
                try {
                    $stripe = new StripeClient($stripeSecretKey);

                    $stripe->subscriptions->update(
                        $user->getStripeSubscriptionId(),
                        ['cancel_at_period_end' => true],
                        ['idempotency_key' => 'delete-account-' . $user->getId()]
                    );
                } catch (ApiErrorException) {
                    $this->addFlash('error', 'Impossible de programmer la suppression du compte. Réessayez.');

                    return $this->redirectToRoute('subscription_manage');
                }
            }

            $deleteAt = $user->getNextBillingDate();
            $user->markDeletionAtPeriodEnd($deleteAt);
            $em->flush();

            $this->sendAccountDeletionScheduledEmail($mailer, $user, $deleteAt);

            $this->addFlash(
                'success',
                'Votre compte sera supprimé automatiquement à la fin de votre période d’abonnement.'
            );

            return $this->redirectToRoute('subscription_manage');
        }

        $email = (string) $user->getEmail();

        $em->remove($user);
        $em->flush();

        $this->sendAccountDeletedEmail($mailer, $email);

        $security->logout(false);

        return $this->redirectToRoute('goodbye');
    }

    private function sendAccountDeletionScheduledEmail(
        MailerInterface $mailer,
        User $user,
        ?\DateTimeInterface $deleteAt,
    ): void {
        try {
            $mailer->send(
                (new TemplatedEmail())
                    ->from(new Address('contact@agentboost-immo.fr', 'AgentBoost'))
                    ->to((string) $user->getEmail())
                    ->subject('Suppression de compte programmée — AgentBoost')
                    ->htmlTemplate('emails/account_deletion_scheduled.html.twig')
                    ->context([
                        'user' => $user,
                        'deleteAt' => $deleteAt,
                        'manageSubscriptionUrl' => $this->generateUrl(
                            'subscription_manage',
                            [],
                            UrlGeneratorInterface::ABSOLUTE_URL
                        ),
                    ])
            );
        } catch (\Throwable) {
            // Ne bloque pas la suppression programmée si l’email échoue.
        }
    }

    private function sendAccountDeletedEmail(MailerInterface $mailer, string $email): void
    {
        try {
            $mailer->send(
                (new TemplatedEmail())
                    ->from(new Address('contact@agentboost-immo.fr', 'AgentBoost'))
                    ->to($email)
                    ->subject('Compte supprimé — AgentBoost')
                    ->htmlTemplate('emails/account_deleted.html.twig')
            );
        } catch (\Throwable) {
            // Ne bloque pas la suppression définitive si l’email échoue.
        }
    }
}
