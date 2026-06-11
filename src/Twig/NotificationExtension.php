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
        private NotificationRepository $notificationRepository,
        private Security $security,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_notifications_count', $this->getUnreadCount(...)),
            new TwigFunction('recent_notifications', $this->getRecentNotifications(...)),
        ];
    }

    public function getUnreadCount(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return 0;
        }

        return $this->notificationRepository->countUnreadForUser($user);
    }

    public function getRecentNotifications(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        return $this->notificationRepository->findRecentForUser($user);
    }
}
