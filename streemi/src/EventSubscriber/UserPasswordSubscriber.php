<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsEntityListener(event: Events::prePersist, method: 'hashPassword', entity: User::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'hashPassword', entity: User::class)]
class UserPasswordSubscriber
{
    public function __construct(
        protected UserPasswordHasherInterface $passwordHasher,
    )
    {
    }

    public function hashPassword(User $user): void
    {
        $plainPassword = $user->getPlainPassword();

        if ($plainPassword) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword(password: $hashedPassword);
            $user->eraseCredentials();

            // Envoie d'emails
        }
    }
}
