<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class CancelPendingPlanController extends AbstractController
{
    #[Route(
        '/subscription/pending-plan/cancel',
        name: 'subscription_cancel_pending_plan',
        methods: ['POST']
    )]
    public function __invoke(Request $request, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('subscription_cancel_pending_plan', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // abonnement pas actif => rien à annuler
        if (!$user->isActive()) {
            return $this->redirectToRoute('subscription_manage');
        }

        // rien en attente => idempotent
        if ($user->getPendingPlan() === null) {
            return $this->redirectToRoute('subscription_manage');
        }

        // ✅ annulation locale uniquement
        $user->setPendingPlan(null);
        $em->flush();

        $this->addFlash('success', 'Changement de plan annulé.');

        return $this->redirectToRoute('subscription_manage');
    }
}
