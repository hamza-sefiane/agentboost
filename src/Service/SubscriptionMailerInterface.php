<?php

namespace App\Service;

use App\Entity\User;

interface SubscriptionMailerInterface
{
    public function sendWelcomeEmail(
        string $to,
        string $prenom
    ): void;

    public function sendActivationEmail(
        User $user,
        \DateTimeInterface $endDate,
    ): void;

    public function sendCancellationEmail(
        User $user,
        \DateTimeInterface $endDate,
    ): void;
}
