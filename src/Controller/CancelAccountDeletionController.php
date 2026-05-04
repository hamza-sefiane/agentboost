<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
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

        $user->cancelDeletionAtPeriodEnd();

        $em->flush();

        $this->addFlash('success', 'La suppression de votre compte a été annulée.');

        return $this->redirectToRoute('subscription_manage');
    }
}