<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_notifications_count', [$this, 'getUnreadCount']),
            new TwigFunction('notifications_unread_count', [$this, 'getUnreadCountForUser']),
            new TwigFunction('latest_notifications', [$this, 'getLatestNotifications']),
        ];
    }

    public function getUnreadCount(): int
    {
        $user = $this->security->getUser();
        if ($user === null) {
            return 0;
        }

        return $this->notificationRepository->count(['user' => $user, 'isRead' => false]);
    }

    public function getUnreadCountForUser(?User $user): int
    {
        if ($user === null) {
            return 0;
        }

        return $this->notificationRepository->count(['user' => $user, 'isRead' => false]);
    }

    public function getLatestNotifications(int $limit = 5): array
    {
        $user = $this->security->getUser();
        if ($user === null) {
            return [];
        }

        return $this->notificationRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
            $limit
        );
    }
}

