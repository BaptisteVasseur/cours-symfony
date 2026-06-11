<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Service\Booking\ReservationStatusManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host/reservations')]
#[IsGranted('ROLE_HOST')]
final class HostReservationController extends AbstractController
{
    #[Route('', name: 'app_host_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $host = $this->getHostUser();

        return $this->render('host_reservation/index.html.twig', [
            'reservations' => $reservationRepository->findPendingForHost($host),
        ]);
    }

    #[Route('/{id}/accept', name: 'app_host_reservation_accept', methods: ['POST'])]
    public function accept(
        Request $request,
        Reservation $reservation,
        ReservationRepository $reservationRepository,
        ReservationStatusManager $reservationStatusManager,
    ): Response {
        $host = $this->getHostUser();
        $reservation = $reservationRepository->findOnePendingForHost($reservation, $host);

        if ($reservation === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('host_accept'.$reservation->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_host_reservation_index', [], Response::HTTP_SEE_OTHER);
        }

        try {
            $reservationStatusManager->accept($reservation, $host);
            $this->addFlash('success', 'La demande de réservation a été acceptée.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_host_reservation_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/reject', name: 'app_host_reservation_reject', methods: ['POST'])]
    public function reject(
        Request $request,
        Reservation $reservation,
        ReservationRepository $reservationRepository,
        ReservationStatusManager $reservationStatusManager,
    ): Response {
        $host = $this->getHostUser();
        $reservation = $reservationRepository->findOnePendingForHost($reservation, $host);

        if ($reservation === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('host_reject'.$reservation->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_host_reservation_index', [], Response::HTTP_SEE_OTHER);
        }

        try {
            $reservationStatusManager->reject($reservation, $host, $request->getPayload()->getString('reason'));
            $this->addFlash('success', 'La demande de réservation a été refusée.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_host_reservation_index', [], Response::HTTP_SEE_OTHER);
    }

    private function getHostUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
