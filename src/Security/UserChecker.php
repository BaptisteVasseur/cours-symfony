<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->getStatus() === 'suspended') {
            throw new CustomUserMessageAccountStatusException('Votre compte a été suspendu. Contactez le support.');
        }

        if ($user->getStatus() !== 'active') {
            throw new CustomUserMessageAccountStatusException('Votre compte n\'est pas encore actif.');
        }
    }
}
