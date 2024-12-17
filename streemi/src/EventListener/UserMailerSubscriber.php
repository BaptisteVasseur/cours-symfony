<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsEntityListener(event: Events::prePersist, entity: User::class)]
readonly class UserMailerSubscriber
{
    public function __construct(
        protected MailerInterface $mailer
    )
    {
    }

    public function prePersist(User $user): void
    {
        $email = (new Email());

        $this->mailer->send($email);
    }
}
