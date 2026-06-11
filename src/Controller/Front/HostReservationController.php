<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Service\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote/reservations')]
#[IsGranted('ROLE_HOST')]
final class HostReservationController extends AbstractController
{
    public function __construct(
        private readonly ReservationService $reservationService,
    ) {}

    #[Route('', name: 'app_host_reservations', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/host/reservations.html.twig', [
            'reservations' => $reservationRepository->findPendingForHost($user),
        ]);
    }

    #[Route('/{id}/confirmer', name: 'app_host_reservation_confirm', methods: ['POST'])]
    public function confirm(Request $request, Reservation $reservation): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('confirm_' . $reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_host_reservations');
        }

        if ($reservation->getProperty()->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        try {
            $this->reservationService->confirm($reservation, $user);
            $this->addFlash('success', 'Réservation confirmée.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_host_reservations');
    }

    #[Route('/{id}/refuser', name: 'app_host_reservation_refuse', methods: ['POST'])]
    public function refuse(Request $request, Reservation $reservation): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('refuse_' . $reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_host_reservations');
        }

        if ($reservation->getProperty()->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $reason = $request->request->get('reason', 'Demande refusée par l\'hôte.');

        try {
            $this->reservationService->cancel($reservation, $user, $reason);
            $this->addFlash('success', 'Demande refusée.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_host_reservations');
    }


    #[Route('/pending-count', name: 'app_host_pending_count', methods: ['GET'])]
    public function pendingCount(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$this->isGranted('ROLE_HOST')) {
            return new Response('0');
        }
        
        $count = $reservationRepository->countPendingForHost($user);
        
        return new Response((string) $count);
    }
}