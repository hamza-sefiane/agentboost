<?php

namespace App\Service;

use App\Entity\User;

class SubscriptionLimiter
{
    public function canCreateEstimation(User $user): bool
    {
        if ($this->isPremium($user)) {
            return true;
        }

        return $user->getMonthlyEstimations() < 3;
    }

    public function canGenerateAd(User $user): bool
    {
        if ($this->isPremium($user)) {
            return true;
        }

        return $user->getMonthlyAiGenerations() < 1;
    }

    public function incrementEstimations(User $user): void
    {
        $user->incrementMonthlyEstimations();
    }

    public function incrementAiGenerations(User $user): void
    {
        $user->incrementMonthlyAiGenerations();
    }

    public function getRemainingEstimations(User $user): int
    {
        if ($this->isPremium($user)) {
            return PHP_INT_MAX;
        }

        return max(0, 3 - $user->getMonthlyEstimations());
    }

    public function getRemainingAiGenerations(User $user): int
    {
        if ($this->isPremium($user)) {
            return PHP_INT_MAX;
        }

        return max(0, 1 - $user->getMonthlyAiGenerations());
    }

    private function isPremium(User $user): bool
    {
        return $user->isActive();
    }
}