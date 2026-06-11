<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationRepository $repo,
    ) {
    }

    /**
     * Create and persist a new notification (caller is responsible for flushing).
     */
    public function notify(User $user, string $message, ?string $link = null, string $channel = 'app'): void
    {
        $n = new Notification();
        $n->setUser($user);
        $n->setTitle($message);
        $n->setChannel($channel);
        $n->setLink($link);
        $n->setType('info');
        $this->em->persist($n);
    }
}
