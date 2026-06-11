<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Form\BookingCancelType;
use App\Form\BookingType;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Security\Voter\BookingVoter;
use App\Service\BookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/bookings', name: 'booking_')]
final class BookingController extends AbstractController
{
    #[Route('/checkout/{id}', name: 'checkout', methods: ['GET', 'POST'])]
    public function checkout(
        Property $property,
        Request $request,
        BookingService $bookingService,
        PropertyRepository $propertyRepository,
    ): Response {
        if ($property->getStatus() !== 'published') {
            throw $this->createNotFoundException('Logement non disponible.');
        }

        // If POST and not logged in → redirect to login with target path
        if ($request->isMethod('POST') && !$this->getUser()) {
            return $this->redirectToRoute('app_login', [
                '_target_path' => $request->getUri(),
            ]);
        }

        // Read query params for pre-fill (available even when not logged in)
        $checkinParam  = $request->query->get('checkin');
        $checkoutParam = $request->query->get('checkout');
        $guestsParam   = $request->query->getInt('guests', 1);

        $checkin  = $checkinParam  ? (\DateTimeImmutable::createFromFormat('Y-m-d', $checkinParam)  ?: null) : null;
        $checkout = $checkoutParam ? (\DateTimeImmutable::createFromFormat('Y-m-d', $checkoutParam) ?: null) : null;
        $guests   = max(1, $guestsParam);

        // Only process the booking form if the user is authenticated
        if ($this->getUser()) {
            $reservation = new Reservation();

            // Pre-fill from query params BEFORE createForm so hidden inputs get the values
            if ($checkin)    { $reservation->setCheckinDate($checkin); }
            if ($checkout)   { $reservation->setCheckoutDate($checkout); }
            if ($guests > 0) { $reservation->setGuestsCount($guests); }

            $form = $this->createForm(BookingType::class, $reservation);
            $form->handleRequest($request);

            // On submit, form values override the pre-filled ones
            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    $bookingService->createBooking(
                        $property,
                        $this->getUser(),
                        $reservation->getCheckinDate(),
                        $reservation->getCheckoutDate(),
                        $reservation->getGuestsCount() ?? 1,
                    );

                    if ($property->isInstantBooking()) {
                        $this->addFlash('booking_confirmed', 'Votre réservation est confirmée !');
                    } else {
                        $this->addFlash('booking_pending', 'Votre demande a bien été envoyée à l\'hôte. Vous serez notifié dès qu\'il aura répondu.');
                    }

                    return $this->redirectToRoute('booking_my_bookings');
                } catch (\RuntimeException $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            }

            $checkin  = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();
            $guests   = $reservation->getGuestsCount() ?? 1;
        } else {
            $form = null;
        }

        $property = $propertyRepository->findOneForDetail($property) ?? $property;

        $nights     = ($checkin && $checkout) ? (int) $checkin->diff($checkout)->days : null;
        $base       = ($nights && $property->getPricePerNight()) ? (float) $property->getPricePerNight() * $nights : null;
        $cleaning   = (float) ($property->getCleaningFee() ?? 0);
        $serviceFee = $base !== null ? round($base * 0.05, 2) : null;
        $deposit    = $property->getSecurityDeposit() !== null ? (float) $property->getSecurityDeposit() : null;
        $total      = $base !== null ? round($base + $cleaning + $serviceFee, 2) : null;

        return $this->render('booking/checkout.html.twig', [
            'property'   => $property,
            'form'       => $form,
            'checkin'    => $checkin,
            'checkout'   => $checkout,
            'guests'     => $guests,
            'nights'     => $nights,
            'base'       => $base,
            'cleaning'   => $cleaning,
            'serviceFee' => $serviceFee,
            'deposit'    => $deposit,
            'total'      => $total,
        ]);
    }

    #[Route('/mes-reservations', name: 'my_bookings', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myBookings(ReservationRepository $reservationRepository): Response
    {
        $reservations = $reservationRepository->findByGuestOrderedByDate($this->getUser());

        $byYear = [];
        foreach ($reservations as $reservation) {
            $year = $reservation->getCheckinDate()?->format('Y') ?? 'Inconnue';
            $byYear[$year][] = $reservation;
        }
        krsort($byYear);

        return $this->render('booking/my-bookings.html.twig', [
            'reservationsByYear' => $byYear,
        ]);
    }

    #[Route('/{id}/annuler', name: 'cancel', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancel(Reservation $reservation, Request $request, BookingService $bookingService): Response
    {
        $this->denyAccessUnlessGranted(BookingVoter::CANCEL, $reservation);

        $form = $this->createForm(BookingCancelType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->isCsrfTokenValid('booking_cancel_' . $reservation->getId(), $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('booking_my_bookings');
            }

            try {
                $preset = $request->request->getString('preset_reason');
                $custom = trim((string) $form->get('cancellationReason')->getData());
                if ($preset === 'other' || $preset === '') {
                    $reason = $custom;
                } else {
                    $reason = $preset . ($custom !== '' ? ' — ' . $custom : '');
                }
                $bookingService->cancel($reservation, $reason ?: '', 'guest');
                $this->addFlash('success', 'Réservation annulée.');
            } catch (\RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('booking_my_bookings');
        }

        return $this->render('booking/cancel.html.twig', [
            'reservation' => $reservation,
            'form'        => $form,
        ]);
    }
}
