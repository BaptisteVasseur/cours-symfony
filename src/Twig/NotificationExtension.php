<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose le nombre de notifications non lues de l'utilisateur connecté, pour la
 * cloche de l'en-tête (G.8).
 */
final class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security               $security,
        private readonly NotificationRepository $notificationRepository,
    )
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_notifications_count', $this->unreadCount(...)),
        ];
    }

    public function unreadCount(): int
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->notificationRepository->countUnreadForUser($user) : 0;
    }
}
