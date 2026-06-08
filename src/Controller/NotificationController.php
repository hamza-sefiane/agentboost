<?php

namespace App\Controller;

use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/notifications')]
final class NotificationController extends AbstractController
{
    #[Route('', name: 'notifications_index', methods: ['GET'])]
    public function index(NotificationRepository $notificationRepository): Response
    {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('notification/index.html.twig', [
            'notifications' => $notificationRepository->findLatestForUser($user),
        ]);
    }

    #[Route('/read-all', name: 'notifications_read_all', methods: ['POST'])]
    public function readAll(
        NotificationRepository $notificationRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([], Response::HTTP_FORBIDDEN);
        }

        $notifications = $notificationRepository->findUnreadForUser($user);

        foreach ($notifications as $notification) {
            $notification->setIsRead(true);
        }

        $entityManager->flush();

        return $this->json([
            'success' => true,
        ]);
    }
}
