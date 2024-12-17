<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsEntityListener(event: Events::prePersist, entity: User::class)]
#[AsEntityListener(event: Events::preUpdate, entity: User::class)]
readonly class UserPasswordSubscriber
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    )
    {
    }

    public function prePersist(User $user): void
    {
        $this->encodePassword($user);
    }

    public function preUpdate(User $user): void
    {
        $this->encodePassword($user);
    }

    private function encodePassword(User $user): void
    {
        $plainPassword = $user->getPlainPassword();

        if (!$plainPassword) {
            return;
        }

        $encodedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);

        $user->setPassword($encodedPassword);
        $user->eraseCredentials();
    }
}
