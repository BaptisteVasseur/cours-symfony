<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Listing;
use App\Service\BookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/booking')]
#[IsGranted('ROLE_USER')]
final class BookingController extends AbstractController
{
    #[Route('', name: 'booking_my', methods: ['GET'])]
    public function myBookings(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        return $this->render('booking/my_bookings.html.twig', [
            'bookings' => $user->getBookings(),
        ]);
    }

    #[Route('/new/{id}', name: 'booking_new', methods: ['POST'])]
    public function new(Listing $listing, Request $request, BookingService $bookingService): Response
    {
        if (!$this->isCsrfTokenValid('booking_new_' . $listing->getId(), $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            return $this->redirectToRoute('app_listing_details', ['id' => $listing->getId()]);
        }

        $checkinStr  = $request->request->get('checkin', '');
        $checkoutStr = $request->request->get('checkout', '');
        $guestsCount = (int) $request->request->get('guestsCount', 1);

        if ($checkinStr === '' || $checkoutStr === '') {
            $this->addFlash('error', 'Les dates d\'arrivée et de départ sont obligatoires.');
            return $this->redirectToRoute('app_listing_details', ['id' => $listing->getId()]);
        }

        try {
            $checkin  = new \DateTime($checkinStr);
            $checkout = new \DateTime($checkoutStr);
        } catch (\Exception) {
            $this->addFlash('error', 'Format de date invalide.');
            return $this->redirectToRoute('app_listing_details', ['id' => $listing->getId()]);
        }

        if ($checkout <= $checkin) {
            $this->addFlash('error', 'La date de départ doit être postérieure à la date d\'arrivée.');
            return $this->redirectToRoute('app_listing_details', ['id' => $listing->getId()]);
        }

        if ($guestsCount < 1) {
            $this->addFlash('error', 'Le nombre de voyageurs doit être au moins 1.');
            return $this->redirectToRoute('app_listing_details', ['id' => $listing->getId()]);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($user === $listing->getHost()) {
            $this->addFlash('error', 'Vous ne pouvez pas réserver votre propre logement.');
            return $this->redirectToRoute('app_listing_details', ['id' => $listing->getId()]);
        }

        try {
            $booking = $bookingService->createBooking($listing, $user, $checkin, $checkout, $guestsCount);
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_listing_details', ['id' => $listing->getId()]);
        }

        if ($booking->getStatus() === 'confirmed') {
            $this->addFlash('success', 'Votre réservation est confirmée !');
        } else {
            $this->addFlash('success', 'Votre demande a été envoyée à l\'hôte. Vous serez notifié dès qu\'il l\'aura traitée.');
        }

        return $this->redirectToRoute('booking_show', ['id' => $booking->getId()]);
    }

    #[Route('/{id}', name: 'booking_show', methods: ['GET'])]
    public function show(Booking $booking): Response
    {
        if ($booking->getGuest() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('booking/show.html.twig', ['booking' => $booking]);
    }

    #[Route('/{id}/cancel', name: 'booking_cancel', methods: ['POST'])]
    public function cancel(Booking $booking, Request $request, BookingService $bookingService): Response
    {
        if ($booking->getGuest() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!in_array($booking->getStatus(), ['pending', 'confirmed'], true)) {
            $this->addFlash('error', 'Cette réservation ne peut plus être annulée.');
            return $this->redirectToRoute('booking_show', ['id' => $booking->getId()]);
        }

        if (!$this->isCsrfTokenValid('cancel_booking_' . $booking->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $reason = trim($request->request->get('cancelReason', ''));
        if ($reason === '') {
            $this->addFlash('error', 'Un motif d\'annulation est obligatoire.');
            return $this->redirectToRoute('booking_show', ['id' => $booking->getId()]);
        }

        $bookingService->cancel($booking, $reason, 'guest');
        $this->addFlash('success', 'Votre réservation a été annulée.');

        return $this->redirectToRoute('booking_show', ['id' => $booking->getId()]);
    }
}
