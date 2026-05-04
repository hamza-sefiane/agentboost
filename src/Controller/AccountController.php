<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccountController extends AbstractController
{
    #[Route('/account/delete', name: 'account_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        EntityManagerInterface $em,
        Security $security,
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
            try {
                if ($user->getStripeSubscriptionId()) {
                    $stripe = new StripeClient($stripeSecretKey);

                    $stripe->subscriptions->update($user->getStripeSubscriptionId(), [
                        'cancel_at_period_end' => true,
                    ]);
                }
            } catch (ApiErrorException) {
                // Abonnement déjà annulé ou supprimé côté Stripe.
                // On continue quand même la suppression côté application.
            }

            $user->markDeletionAtPeriodEnd($user->getNextBillingDate());

            $em->flush();

            $this->addFlash('success', 'Votre compte sera supprimé automatiquement à la fin de votre abonnement.');

            return $this->redirectToRoute('subscription_manage');
        }

        $em->remove($user);
        $em->flush();

        $logoutResponse = $security->logout(false);

        return $logoutResponse ?? $this->redirectToRoute('home');
    }
}