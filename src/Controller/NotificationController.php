<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/notifications')]
final class NotificationController extends AbstractController
{
    #[Route('', name: 'notifications_index', methods: ['GET'])]
    public function index(NotificationRepository $notificationRepository): Response
    {
        $user = $this->getAuthenticatedUser();

        return $this->render('notification/index.html.twig', [
            'notifications' => $notificationRepository->findLatestForUser($user, 50),
        ]);
    }

    #[Route('/read-all', name: 'notifications_read_all', methods: ['POST'])]
    public function readAll(
        Request $request,
        NotificationRepository $notificationRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getAuthenticatedUser();

        if (!$request->isXmlHttpRequest()) {
            if (!$this->isCsrfTokenValid('notifications_read_all', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }
        }

        $notifications = $notificationRepository->findUnreadForUser($user, 100);

        foreach ($notifications as $notification) {
            $notification->setIsRead(true);
        }

        $entityManager->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true]);
        }

        $this->addFlash('success', 'Toutes les notifications ont été marquées comme lues.');

        return $this->redirectToRoute('notifications_index');
    }

    #[Route('/{id}/delete', name: 'notifications_delete', methods: ['POST'])]
    public function delete(
        Notification $notification,
        Request $request,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        $user = $this->getAuthenticatedUser();

        if ($notification->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('delete_notification_' . $notification->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $entityManager->remove($notification);
        $entityManager->flush();

        $this->addFlash('success', 'Notification supprimée.');

        return $this->redirectToRoute('notifications_index');
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
