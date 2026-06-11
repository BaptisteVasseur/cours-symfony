<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use App\Service\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote/reservations')]
#[IsGranted('ROLE_USER')]
final class HostReservationController extends AbstractController
{
    #[Route('', name: 'app_host_reservations', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/host/reservations.html.twig', [
            'pendingReservations' => $reservationRepository->findPendingForHost($user),
            'activeReservations' => $reservationRepository->findActiveForHost($user),
        ]);
    }

    #[Route('/{id}/confirmer', name: 'app_host_reservation_confirm', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function confirm(
        Reservation $reservation,
        ReservationService $reservationService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $reservationService->confirm($reservation, $user);
            $this->addFlash('success', 'La réservation a été confirmée.');
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_host_reservations');
    }

    #[Route('/{id}/refuser', name: 'app_host_reservation_reject', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function reject(
        Reservation $reservation,
        Request $request,
        ReservationService $reservationService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $reason = trim((string) $request->request->get('reason', ''));
        if ($reason === '') {
            $this->addFlash('error', 'Un motif de refus est obligatoire.');

            return $this->redirectToRoute('app_host_reservations');
        }

        try {
            $reservationService->reject($reservation, $user, $reason);
            $this->addFlash('success', 'La demande a été refusée.');
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_host_reservations');
    }
}
