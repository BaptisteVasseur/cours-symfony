<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function notify(User $user, string $title, string $body, ?string $linkUrl = null): void
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setTitle($title);
        $notification->setContent($body);
        $notification->setIsRead(false);
        $notification->setChannel('in_app');
        $notification->setType('info');
        $notification->setLinkUrl($linkUrl);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }
}
