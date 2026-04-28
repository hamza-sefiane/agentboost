<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccountController extends AbstractController
{
    #[Route('/account/delete', name: 'account_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('delete_account', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($user->isActive()) {
            $this->addFlash('danger', 'Impossible de supprimer votre compte : un abonnement est encore actif. Résiliez-le avant.');
            return $this->redirectToRoute('subscription_manage');
        }

        $em->remove($user);
        $em->flush();

        $request->getSession()->invalidate();

        return $this->redirectToRoute('home');
    }
}