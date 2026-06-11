<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
final class NotificationController extends AbstractController
{
    #[Route('', name: 'app_notifications_index', methods: ['GET'])]
    public function index(NotificationRepository $repo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $notifications = $repo->findUnreadForUser($user);

        // Mark all as read
        foreach ($notifications as $n) {
            $n->setIsRead(true);
        }
        $em->flush();

        return $this->render('front/notifications/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }
}
