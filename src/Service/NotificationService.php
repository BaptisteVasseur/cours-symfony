<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function create(User $user, string $type, string $title, ?string $content = null): void
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setContent($content);
        $notification->setChannel('app');
        $notification->setIsRead(false);
        $this->em->persist($notification);
    }
}
