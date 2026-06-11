<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationRepository $notificationRepository,
        private readonly RealtimePublisher $realtimePublisher,
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

        $this->realtimePublisher->publishToUser($user, 'notification.created', [
            'notificationId' => $notification->getId()?->toRfc4122(),
            'title' => $notification->getTitle(),
            'content' => $notification->getContent(),
            'linkUrl' => $notification->getLinkUrl(),
            'createdAt' => $notification->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'createdAtLabel' => $notification->getCreatedAt()?->format('d/m/Y H:i'),
            'unreadCount' => $this->notificationRepository->count(['user' => $user, 'isRead' => false]),
        ]);
    }
}
