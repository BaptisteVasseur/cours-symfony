<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class NotificationController extends AbstractController
{
    #[Route('/notifications', name: 'app_notifications', methods: ['GET'])]
    public function index(NotificationService $notifications): Response
    {
        $user = $this->getUser();
        $items = $notifications->latest($user, 30);
        $notifications->markAllAsRead($user);

        return $this->render('notification/index.html.twig', [
            'notifications' => $items,
        ]);
    }

    #[Route('/notifications/read', name: 'app_notifications_read', methods: ['POST'])]
    public function markRead(Request $request, NotificationService $notifications): Response
    {
        if ($this->isCsrfTokenValid('notifications_read', (string) $request->request->get('_token'))) {
            $notifications->markAllAsRead($this->getUser());
        }

        return $this->redirectToRoute('app_notifications');
    }
}
