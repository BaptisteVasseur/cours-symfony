<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class NotificationController extends AbstractController
{
    #[Route('/notifications/unread', name: 'app_notifications_unread', methods: ['GET'])]
    public function unread(NotificationRepository $notificationRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['count' => 0, 'items' => []]);
        }

        $notifications = $notificationRepository->findRecentForUser($user);
        $count = $notificationRepository->countUnreadForUser($user);

        $items = array_map(static fn ($n) => [
            'id' => $n->getId(),
            'title' => $n->getTitle(),
            'content' => $n->getContent(),
            'isRead' => $n->isRead(),
            'createdAt' => $n->getCreatedAt()?->format('d/m/Y H:i'),
        ], $notifications);

        return new JsonResponse(['count' => $count, 'items' => $items]);
    }

    #[Route('/notifications/mark-read', name: 'app_notifications_mark_read', methods: ['GET'])]
    public function markAllRead(NotificationRepository $notificationRepository): Response
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            $notificationRepository->markAllReadForUser($user);
        }

        return $this->redirectToRoute('app_home');
    }
}
