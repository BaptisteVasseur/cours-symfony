<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class NotificationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private NotificationRepository $repo,
        private Security $security,
    ) {
    }

    public function getGlobals(): array
    {
        $user = $this->security->getUser();

        return [
            'unreadNotificationCount' => $user instanceof User
                ? $this->repo->countUnreadForUser($user)
                : 0,
        ];
    }
}
