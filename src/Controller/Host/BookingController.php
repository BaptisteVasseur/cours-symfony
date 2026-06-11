<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\Reservation;
use App\Entity\User;
use App\Exception\BookingConflictException;
use App\Repository\BookingRepository;
use App\Security\Voter\BookingVoter;
use App\Service\BookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_HOST')]
final class BookingController extends AbstractController
{
    #[Route('/host/bookings', name: 'app_host_bookings', methods: ['GET'])]
    #[Route('/compte/hote/reservations', name: 'app_host_reservations', methods: ['GET'])]
    public function index(
        Request $request,
        BookingRepository $bookingRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $status = $request->query->get('status');
        if ($status === 'all' || !in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'], true)) {
            $status = null;
        }

        $reservations = $bookingRepository->findByHostForListing($user, $status);

        return $this->render('front/host_dashboard/reservations.html.twig', [
            'reservations' => $reservations,
            'currentStatus' => $status ?? 'all',
        ]);
    }

    #[Route('/host/booking/{id}/confirm', name: 'app_host_booking_confirm', methods: ['POST'])]
    #[Route('/compte/hote/reservations/{id}/accepter', name: 'app_host_reservation_accept', methods: ['POST'])]
    #[IsGranted(BookingVoter::MANAGE, subject: 'booking')]
    public function confirm(
        Request $request,
        Reservation $booking,
        BookingService $bookingService,
    ): Response {
        if (!$this->isCsrfTokenValid('accept'.$booking->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_host_bookings');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $bookingService->confirm($booking, $user);
            $this->addFlash('success', 'La réservation a été acceptée avec succès.');
        } catch (\LogicException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_host_bookings');
    }

    #[Route('/host/booking/{id}/refuse', name: 'app_host_booking_refuse', methods: ['POST'])]
    #[Route('/compte/hote/reservations/{id}/refuser', name: 'app_host_reservation_decline', methods: ['POST'])]
    #[IsGranted(BookingVoter::MANAGE, subject: 'booking')]
    public function refuse(
        Request $request,
        Reservation $booking,
        BookingService $bookingService,
    ): Response {
        if (!$this->isCsrfTokenValid('decline'.$booking->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_host_bookings');
        }

        $reason = trim((string) $request->request->get('cancellation_reason'));
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $bookingService->refuse($booking, $user, $reason);
            $this->addFlash('success', 'La demande de réservation a été refusée.');
        } catch (\LogicException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_host_bookings');
    }

    #[Route('/compte/hote/reservations/{id}/annuler', name: 'app_host_reservation_cancel', methods: ['POST'])]
    #[IsGranted(BookingVoter::MANAGE, subject: 'booking')]
    public function cancel(
        Request $request,
        Reservation $booking,
        BookingService $bookingService,
    ): Response {
        if (!$this->isCsrfTokenValid('cancel'.$booking->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_host_bookings');
        }

        $reason = trim((string) $request->request->get('cancellation_reason'));
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $bookingService->cancel($booking, $user, $reason);
            $this->addFlash('success', 'La réservation a été annulée avec succès.');
        } catch (\LogicException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_host_bookings');
    }
}
