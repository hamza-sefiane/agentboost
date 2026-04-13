<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ActiveSubscriberVoter extends Voter
{
    public const ACCESS_DASHBOARD = 'ACCESS_DASHBOARD';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::ACCESS_DASHBOARD;
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token
    ): bool {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return $user->isActive();
    }
}
