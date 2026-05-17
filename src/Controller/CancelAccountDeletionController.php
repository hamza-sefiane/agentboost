<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
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
                $this->addFlash('error', 'Impossible d’annuler la suppression du compte. Réessayez ou contactez le support.');

                return $this->redirectToRoute('subscription_manage');
            }
        }

        $user->cancelDeletionAtPeriodEnd();

        if ($user->getNextBillingDate()) {
            $user->activateSubscription($user->getNextBillingDate());
        }

        $em->flush();

        try {
            $mailer->send(
                (new Email())
                    ->from('AgentBoost <no-reply@agentboost-immo.fr>')
                    ->to($user->getEmail())
                    ->subject('Suppression de compte annulée — AgentBoost')
                    ->html(
                        '<p>Bonjour,</p>
                        <p>Votre demande de suppression de compte a bien été annulée.</p>
                        <p>Votre abonnement reste actif et vos accès AgentBoost sont conservés.</p>
                        <p>— L’équipe AgentBoost</p>'
                    )
            );
        } catch (\Throwable) {
        }

        $this->addFlash(
            'success',
            'La suppression de votre compte a été annulée. Votre abonnement reste actif.'
        );

        return $this->redirectToRoute('subscription_manage');
    }
}
