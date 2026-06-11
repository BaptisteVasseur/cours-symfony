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
        private readonly EntityManagerInterface $em,
        private readonly NotificationRepository $notificationRepository,
    ) {
    }

    public function push(User $user, string $type, string $title, string $content): Notification
    {
        $notification = (new Notification())
            ->setUser($user)
            ->setType($type)
            ->setChannel('in_app')
            ->setTitle($title)
            ->setContent($content)
            ->setIsRead(false)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($notification);

        return $notification;
    }

    public function countUnread(User $user): int
    {
        return $this->notificationRepository->countUnreadForUser($user);
    }

    public function latest(User $user, int $limit = 10): array
    {
        return $this->notificationRepository->findLatestForUser($user, $limit);
    }

    public function markAllAsRead(User $user): void
    {
        $this->notificationRepository->markAllAsReadForUser($user);
    }
}
