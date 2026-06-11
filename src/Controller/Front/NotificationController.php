<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Centre de notifications in-app (énoncé G.8).
 */
#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
final class NotificationController extends AbstractController
{
    #[Route('', name: 'app_notifications', methods: ['GET'])]
    public function index(NotificationRepository $notificationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/notification/index.html.twig', [
            'notifications' => $notificationRepository->findRecentForUser($user),
        ]);
    }

    #[Route('/lire', name: 'app_notifications_mark_read', methods: ['POST'])]
    public function markAllRead(Request $request, NotificationRepository $notificationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('notifications_read', (string)$request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $notificationRepository->markAllReadForUser($user);
        $this->addFlash('success', 'Toutes vos notifications ont été marquées comme lues.');

        return $this->redirectToRoute('app_notifications');
    }
}
