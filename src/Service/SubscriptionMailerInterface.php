<?php

namespace App\Service;

interface SubscriptionMailerInterface
{
    public function sendWelcomeEmail(
        string $to,
        string $prenom
    ): void;

    public function sendActivationEmail(
        string $to,
        string $prenom,
        string $plan
    ): void;

    public function sendCancellationEmail(
        string $to,
        string $prenom,
        \DateTimeInterface $endDate
    ): void;
}