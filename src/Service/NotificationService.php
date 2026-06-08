<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function info(User $user, string $title, string $message): Notification
    {
        return $this->create($user, $title, $message, Notification::TYPE_INFO);
    }

    public function success(User $user, string $title, string $message): Notification
    {
        return $this->create($user, $title, $message, Notification::TYPE_SUCCESS);
    }

    public function warning(User $user, string $title, string $message): Notification
    {
        return $this->create($user, $title, $message, Notification::TYPE_WARNING);
    }

    public function error(User $user, string $title, string $message): Notification
    {
        return $this->create($user, $title, $message, Notification::TYPE_ERROR);
    }

    public function create(User $user, string $title, string $message, string $type = Notification::TYPE_INFO): Notification
    {
        $notification = new Notification();
        $notification
            ->setUser($user)
            ->setTitle($title)
            ->setMessage($message)
            ->setType($type)
            ->setIsRead(false);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }
}
