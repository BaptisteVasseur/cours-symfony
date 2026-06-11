<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_notification_count', $this->countUnread(...)),
        ];
    }

    public function countUnread(?User $user): int
    {
        if ($user === null) {
            return 0;
        }

        return (int) $this->notificationRepository->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
