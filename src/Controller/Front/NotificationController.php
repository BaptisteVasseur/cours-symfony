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
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Cloche de l'en-tête. Rendue en sous-requête via render(controller(...))
     * pour disposer du compteur et des dernières notifications sur toutes les
     * pages, sans dupliquer la requête dans chaque contrôleur.
     */
    public function bell(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new Response('');
        }

        return $this->render('layout/partials/app/notification_bell.html.twig', [
            'notifications' => $this->notificationRepository->findRecentInApp($user, 8),
            'unreadCount' => $this->notificationRepository->countUnreadInApp($user),
        ]);
    }

    #[Route('', name: 'app_notifications_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/notification/index.html.twig', [
            'notifications' => $this->notificationRepository->findRecentInApp($user, 50),
        ]);
    }

    #[Route('/lire-tout', name: 'app_notifications_read_all', methods: ['POST'])]
    public function readAll(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        if (!$this->isCsrfTokenValid('notifications-read-all', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $this->notificationRepository->markAllReadInApp($user);

        return $this->redirectToRoute('app_notifications_index');
    }

    /**
     * Marque une notification comme lue puis redirige vers sa cible.
     */
    #[Route('/{id}/ouvrir', name: 'app_notification_open', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function open(Notification $notification): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $notification->getUser()?->getId()?->equals($user->getId()) !== true) {
            throw $this->createAccessDeniedException();
        }

        if (!$notification->isRead()) {
            $notification->setIsRead(true);
            $this->entityManager->flush();
        }

        return $this->redirect($notification->getLinkUrl() ?? $this->generateUrl('app_notifications_index'));
    }
}
