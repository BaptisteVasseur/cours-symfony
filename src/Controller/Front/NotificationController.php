<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
final class NotificationController extends AbstractController
{
    public function bell(NotificationRepository $notificationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new Response('');
        }

        return $this->render('layout/partials/app/notification_bell.html.twig', [
            'notifications' => $notificationRepository->findRecentForUser($user, 8),
            'unreadCount' => $notificationRepository->countUnreadForUser($user),
        ]);
    }

    #[Route('', name: 'app_notifications_index', methods: ['GET'])]
    public function index(NotificationRepository $notificationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/notification/index.html.twig', [
            'notifications' => $notificationRepository->findRecentForUser($user, 50),
            'unreadCount' => $notificationRepository->countUnreadForUser($user),
        ]);
    }

    #[Route('/read-all', name: 'app_notifications_read_all', methods: ['POST'])]
    public function markAllRead(
        Request $request,
        NotificationRepository $notificationRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('read_all_notifications', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $notificationRepository->markAllReadForUser($user);

        return $this->redirectToRoute('app_notifications_index');
    }

    #[Route('/{id}/read', name: 'app_notifications_read', methods: ['POST'])]
    public function markRead(
        Request $request,
        Notification $notification,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $notification->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('read_notification_'.$notification->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $notification->setIsRead(true);
        $entityManager->flush();

        $referer = $request->headers->get('Referer');

        return $this->redirect($referer ?: $this->generateUrl('app_notifications_index'));
    }
}
