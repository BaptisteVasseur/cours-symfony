<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    #[Route('', name: 'app_notifications_index', methods: ['GET'])]
    public function index(NotificationRepository $repository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $notifications = $repository->findRecentByUser($user, 20);
        $unreadCount = $repository->countUnreadByUser($user);

        return $this->render('front/notifications/index.html.twig', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    #[Route('/unread-count', name: 'app_notifications_unread_count', methods: ['GET'])]
    public function unreadCount(NotificationRepository $repository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['count' => 0]);
        }

        $count = $repository->countUnreadByUser($user);

        return $this->json(['count' => $count]);
    }

    #[Route('/{id}/mark-read', name: 'app_notifications_mark_read', methods: ['POST'])]
    public function markRead(string $id, NotificationRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $notification = $repository->find($id);
        if ($notification && $notification->getUser() === $user) {
            $notification->setIsRead(true);
            $em->flush();
        }

        return $this->json(['success' => true]);
    }

    #[Route('/mark-all-read', name: 'app_notifications_mark_all_read', methods: ['POST'])]
    public function markAllRead(NotificationRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $unread = $repository->findUnreadByUser($user);
        foreach ($unread as $notification) {
            $notification->setIsRead(true);
        }
        $em->flush();

        return $this->json(['success' => true]);
    }
}