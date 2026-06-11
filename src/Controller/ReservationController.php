<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Listing;
use App\Entity\Booking;
use App\Enum\BookingStatus;
use App\Exception\UnavailableDatesException;
use App\Security\Voter\BookingVoter;
use App\Service\AvailabilityService;
use App\Service\BookingService;
use App\Service\PricingService;
use App\ValueObject\DateRange;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ReservationController extends AbstractController
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
        private readonly BookingService $bookingService,
    ) {
    }

    #[Route('/listing/{id}/book', name: 'app_reservation_new', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function checkout(Listing $listing, Request $request): Response
    {
        $range = $this->buildRange($request->query->get('checkin'), $request->query->get('checkout'));
        $guests = max(1, (int) $request->query->get('guests', 1));

        $quote = null;
        $available = null;
        $unavailableReason = null;

        if ($range !== null) {
            $available = $this->availability->isAvailable($listing, $range, $guests);
            $unavailableReason = $available ? null : $this->availability->unavailableReason($listing, $range, $guests);
            $quote = $this->pricing->quote($listing, $range);
        }

        return $this->render('reservation/checkout.html.twig', [
            'listing' => $listing,
            'range' => $range,
            'guests' => $guests,
            'quote' => $quote,
            'available' => $available,
            'unavailableReason' => $unavailableReason,
        ]);
    }

    #[Route('/listing/{id}/book', name: 'app_reservation_create', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function create(Listing $listing, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('reservation_' . $listing->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $range = $this->buildRange($request->request->get('checkin'), $request->request->get('checkout'));
        $guests = max(1, (int) $request->request->get('guests', 1));

        if ($range === null) {
            $this->addFlash('error', 'Veuillez sélectionner des dates valides.');

            return $this->redirectToRoute('app_listing_show', ['id' => $listing->getId()]);
        }

        try {
            $booking = $this->bookingService->createBooking($listing, $this->getUser(), $range, $guests);
        } catch (UnavailableDatesException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_reservation_new', [
                'id' => $listing->getId(),
                'checkin' => $range->checkIn->format('Y-m-d'),
                'checkout' => $range->checkOut->format('Y-m-d'),
                'guests' => $guests,
            ]);
        }

        $this->addFlash('success', $booking->getBookingStatus() === BookingStatus::Confirmed
            ? 'Réservation confirmée ! Un email de confirmation vous a été envoyé.'
            : 'Demande envoyée à l\'hôte. Vous serez notifié de sa réponse.');

        return $this->redirectToRoute('app_my_bookings');
    }

    #[Route('/booking/{id}/cancel', name: 'app_booking_cancel', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function cancel(Booking $booking, Request $request): Response
    {
        $this->denyAccessUnlessGranted(BookingVoter::CANCEL, $booking);

        if (!$this->isCsrfTokenValid('cancel_' . $booking->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $reason = trim((string) $request->request->get('reason'));
        if ($reason === '') {
            $this->addFlash('error', 'Un motif d\'annulation est obligatoire.');

            return $this->redirectToRoute('app_booking_show', ['id' => $booking->getId()]);
        }

        if (!in_array($booking->getBookingStatus(), [BookingStatus::Pending, BookingStatus::Confirmed], true)) {
            $this->addFlash('error', 'Cette réservation ne peut plus être annulée.');

            return $this->redirectToRoute('app_booking_show', ['id' => $booking->getId()]);
        }

        $this->bookingService->cancel($booking, $this->getUser(), $reason);
        $this->addFlash('success', 'Réservation annulée. Les dates ont été libérées.');

        return $this->redirectToRoute('app_booking_show', ['id' => $booking->getId()]);
    }

    private function buildRange(mixed $checkIn, mixed $checkOut): ?DateRange
    {
        if (!is_string($checkIn) || !is_string($checkOut) || $checkIn === '' || $checkOut === '') {
            return null;
        }

        try {
            return DateRange::fromStrings($checkIn, $checkOut);
        } catch (\Exception) {
            return null;
        }
    }
}
