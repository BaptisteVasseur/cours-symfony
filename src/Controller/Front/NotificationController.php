<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
final class NotificationController extends AbstractController
{
    #[Route('/json', name: 'app_notifications_json', methods: ['GET'])]
    public function jsonList(
        NotificationRepository $notificationRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['notifications' => []]);
        }

        $notifications = $notificationRepository->findForUser($user);

        foreach ($notifications as $notification) {
            if (!$notification->isRead()) {
                $notification->setIsRead(true);
            }
        }
        $em->flush();

        return $this->json([
            'notifications' => array_map(fn ($n) => [
                'title'     => $n->getTitle(),
                'content'   => $n->getContent(),
                'type'      => $n->getType(),
                'isRead'    => $n->isRead(),
                'createdAt' => $n->getCreatedAt()?->format('d/m/Y à H:i'),
            ], $notifications),
        ]);
    }
}
