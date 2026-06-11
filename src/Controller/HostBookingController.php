<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Booking;
use App\Enum\BookingStatus;
use App\Exception\UnavailableDatesException;
use App\Repository\BookingRepository;
use App\Security\Voter\BookingVoter;
use App\Service\BookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host/bookings')]
#[IsGranted('ROLE_HOST')]
final class HostBookingController extends AbstractController
{
    public function __construct(private readonly BookingService $bookingService)
    {
    }

    #[Route('', name: 'app_host_bookings', methods: ['GET'])]
    public function index(BookingRepository $bookingRepository): Response
    {
        return $this->render('host/bookings.html.twig', [
            'pendingBookings' => $bookingRepository->findPendingForHost($this->getUser()),
        ]);
    }

    #[Route('/{id}/accept', name: 'app_host_booking_accept', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function accept(Booking $booking, Request $request): Response
    {
        $this->denyAccessUnlessGranted(BookingVoter::MODERATE, $booking);
        $this->assertCsrf($request, 'moderate_' . $booking->getId());

        if ($booking->getBookingStatus() !== BookingStatus::Pending) {
            $this->addFlash('error', 'Cette demande n\'est plus en attente.');

            return $this->redirectToRoute('app_host_bookings');
        }

        try {
            $this->bookingService->confirm($booking, $this->getUser());
            $this->addFlash('success', 'Demande acceptée : la réservation est confirmée.');
        } catch (UnavailableDatesException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_host_bookings');
    }

    #[Route('/{id}/reject', name: 'app_host_booking_reject', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function reject(Booking $booking, Request $request): Response
    {
        $this->denyAccessUnlessGranted(BookingVoter::MODERATE, $booking);
        $this->assertCsrf($request, 'moderate_' . $booking->getId());

        $reason = trim((string) $request->request->get('reason'));
        if ($reason === '') {
            $this->addFlash('error', 'Le motif de refus est obligatoire.');

            return $this->redirectToRoute('app_host_bookings');
        }

        if ($booking->getBookingStatus() !== BookingStatus::Pending) {
            $this->addFlash('error', 'Cette demande n\'est plus en attente.');

            return $this->redirectToRoute('app_host_bookings');
        }

        $this->bookingService->reject($booking, $this->getUser(), $reason);
        $this->addFlash('success', 'Demande refusée. Le voyageur a été notifié.');

        return $this->redirectToRoute('app_host_bookings');
    }

    private function assertCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
