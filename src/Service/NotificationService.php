<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function create(User $user, string $title, string $content, ?string $link = null): void
    {
        $notification = (new Notification())
            ->setUser($user)
            ->setType('info')
            ->setTitle($title)
            ->setContent($content)
            ->setLink($link);

        $this->em->persist($notification);
        $this->em->flush();
    }
}
