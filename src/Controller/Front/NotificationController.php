<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Service\RealtimePublisher;
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
    #[Route('', name: 'app_notifications_index', methods: ['GET'])]
    public function index(
        NotificationRepository $notificationRepository,
        EntityManagerInterface $entityManager,
        RealtimePublisher $realtimePublisher,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $unreadNotifications = $notificationRepository->findBy([
            'user' => $user,
            'isRead' => false,
        ]);

        if (count($unreadNotifications) > 0) {
            foreach ($unreadNotifications as $notification) {
                $notification->setIsRead(true);
            }
            $entityManager->flush();
            $realtimePublisher->publishToUser($user, 'notifications.read', [
                'unreadCount' => 0,
            ]);
        }

        $notifications = $notificationRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('front/notification/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }

    #[Route('/{id}/read', name: 'app_notification_read', methods: ['POST'])]
    public function read(
        Notification $notification,
        EntityManagerInterface $entityManager,
        NotificationRepository $notificationRepository,
        RealtimePublisher $realtimePublisher,
        Request $request,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $notification->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $token = $request->request->get('_token') ?? $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('read' . $notification->getId(), (string) $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (!$notification->isRead()) {
            $notification->setIsRead(true);
            $entityManager->flush();
            $realtimePublisher->publishToUser($user, 'notifications.read', [
                'unreadCount' => $notificationRepository->count(['user' => $user, 'isRead' => false]),
            ]);
        }

        $isAjax = $request->isXmlHttpRequest() 
            || $request->headers->get('X-Requested-With') === 'XMLHttpRequest'
            || str_contains($request->headers->get('Accept', ''), 'application/json');

        if ($isAjax) {
            return $this->json([
                'success' => true,
                'unreadCount' => $notificationRepository->count(['user' => $user, 'isRead' => false]),
            ]);
        }

        $linkUrl = $notification->getLinkUrl();
        if ($linkUrl !== null && $linkUrl !== '') {
            return $this->redirect($linkUrl);
        }

        $referer = $request->headers->get('referer');
        if ($referer !== null && str_contains($referer, $request->getHost())) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_notifications_index');
    }

    #[Route('/mark-all-read', name: 'app_notifications_mark_all_read', methods: ['POST'])]
    public function markAllRead(
        NotificationRepository $notificationRepository,
        EntityManagerInterface $entityManager,
        RealtimePublisher $realtimePublisher,
        Request $request,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $token = $request->request->get('_token') ?? $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('mark_all_read', (string) $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $unreadNotifications = $notificationRepository->findBy([
            'user' => $user,
            'isRead' => false,
        ]);

        foreach ($unreadNotifications as $notification) {
            $notification->setIsRead(true);
        }

        $entityManager->flush();
        $realtimePublisher->publishToUser($user, 'notifications.read', [
            'unreadCount' => 0,
        ]);

        $isAjax = $request->isXmlHttpRequest() 
            || $request->headers->get('X-Requested-With') === 'XMLHttpRequest'
            || str_contains($request->headers->get('Accept', ''), 'application/json');

        if ($isAjax) {
            return $this->json([
                'success' => true,
                'unreadCount' => 0,
            ]);
        }

        $this->addFlash('success', 'Toutes les notifications ont été marquées comme lues.');

        $referer = $request->headers->get('referer');
        if ($referer !== null && str_contains($referer, $request->getHost())) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_notifications_index');
    }
}
