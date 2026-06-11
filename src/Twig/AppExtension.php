<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Service\NotificationService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;


final class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly NotificationService $notifications,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_notifications_count', [$this, 'unreadNotificationsCount']),
        ];
    }

    public function unreadNotificationsCount(): int
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->notifications->countUnread($user) : 0;
    }
}
