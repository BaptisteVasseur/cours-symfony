<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationStatut;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    #[Route('', name: 'app_notification_index', methods: ['GET'])]
    public function index(NotificationRepository $notifications): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications->trouverPourUtilisateur($user),
            'non_lues' => $notifications->compterNonLues($user),
        ]);
    }

    #[Route('/{id}/lire', name: 'app_notification_read', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function read(Notification $notification, Request $request, NotificationService $notificationService, EntityManagerInterface $entityManager): RedirectResponse
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        if ($notification->utilisateur->id !== $user->id) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('notification_read_'.$notification->id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action expiree. Reessayez.');

            return $this->redirectToRoute('app_notification_index');
        }

        $notificationService->marquerCommeLue($notification);
        $entityManager->flush();

        if ($notification->lienAction !== null && str_starts_with($notification->lienAction, '/')) {
            return $this->redirect($notification->lienAction);
        }

        return $this->redirectToRoute('app_notification_index');
    }

    #[Route('/tout-lire', name: 'app_notification_read_all', methods: ['POST'])]
    public function readAll(Request $request, NotificationRepository $notifications, NotificationService $notificationService, EntityManagerInterface $entityManager): RedirectResponse
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        if (!$this->isCsrfTokenValid('notification_read_all', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action expiree. Reessayez.');

            return $this->redirectToRoute('app_notification_index');
        }

        foreach ($notifications->trouverPourUtilisateur($user) as $notification) {
            if ($notification->statut === NotificationStatut::NON_LUE) {
                $notificationService->marquerCommeLue($notification);
            }
        }

        $entityManager->flush();
        $this->addFlash('success', 'Toutes les notifications ont ete marquees comme lues.');

        return $this->redirectToRoute('app_notification_index');
    }
}
