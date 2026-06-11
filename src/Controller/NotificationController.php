<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
final class NotificationController extends AbstractController
{
    #[Route('/mark-read/{id}', name: 'notification_mark_read', methods: ['GET', 'POST'])]
    public function markRead(Notification $notification, EntityManagerInterface $em): Response
    {
        if ($notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $notification->setIsRead(true);
        $em->flush();

        $link = $notification->getLink();
        return $link ? $this->redirect($link) : $this->redirectToRoute('app_home');
    }

    #[Route('/feed', name: 'notification_feed', methods: ['GET'])]
    public function feed(NotificationRepository $repo): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user          = $this->getUser();
        $notifications = $repo->findRecentForUser($user);
        $unread        = $repo->countUnreadForUser($user);

        $data = array_map(fn($n) => [
            'id'        => $n->getId(),
            'title'     => $n->getTitle(),
            'content'   => $n->getContent(),
            'isRead'    => $n->isRead(),
            'link'      => $n->getLink(),
            'createdAt' => $n->getCreatedAt()->format('d/m/Y H:i'),
        ], $notifications);

        return new JsonResponse(['notifications' => $data, 'unread' => $unread]);
    }

    #[Route('/mark-all-read', name: 'notification_mark_all_read', methods: ['POST'])]
    public function markAllRead(NotificationRepository $repo, EntityManagerInterface $em, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('mark_all_read', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var \App\Entity\User $user */
        $user          = $this->getUser();
        $notifications = $repo->findRecentForUser($user);

        foreach ($notifications as $n) {
            $n->setIsRead(true);
        }
        $em->flush();

        return new JsonResponse(['success' => true]);
    }
}
