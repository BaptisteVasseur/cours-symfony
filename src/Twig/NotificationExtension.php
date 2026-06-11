<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Service\NotificationService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_notifications_count', $this->countUnread(...)),
        ];
    }

    public function countUnread(User $user): int
    {
        return $this->notificationService->countUnread($user);
    }
}
