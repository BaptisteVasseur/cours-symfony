<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\Reservation;
use App\Form\BookingCancelType;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Service\BookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host/dashboard', name: 'host_')]
#[IsGranted('ROLE_HOST')]
final class HostDashboardController extends AbstractController
{
    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function dashboard(
        ReservationRepository $reservationRepository,
        PropertyRepository $propertyRepository,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $pendingReservations = $reservationRepository->findPendingByHost($user);
        $allReservations     = $reservationRepository->findAllByHost($user);
        $properties          = $propertyRepository->findByHost($user);

        // Timeline: 60 days starting today
        $today     = new \DateTimeImmutable('today');
        $timelineDays = [];
        $frMonthsShort = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'];
        $frDaysShort   = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
        for ($i = 0; $i < 60; $i++) {
            $d = $today->modify("+{$i} days");
            $timelineDays[] = [
                'key'       => $d->format('Y-m-d'),
                'day'       => (int) $d->format('j'),
                'dayLabel'  => $frDaysShort[(int) $d->format('N') - 1],
                'month'     => $frMonthsShort[(int) $d->format('n') - 1],
                'isToday'   => $i === 0,
                'isWeekend' => (int) $d->format('N') >= 6,
                'isFirst'   => (int) $d->format('j') === 1,
            ];
        }

        // Build per-property reservation segments for the timeline
        // segment: { checkinKey, checkoutKey, status, guestName, nights }
        $propertySegments = []; // propertyId => [segments]
        foreach ($properties as $property) {
            $propertySegments[(string) $property->getId()] = [];
        }

        $windowEnd = $today->modify('+60 days');

        // Build a Y-m-d → column index map for O(1) offset lookups
        $dayIndex = [];
        foreach ($timelineDays as $idx => $d) {
            $dayIndex[$d['key']] = $idx;
        }

        foreach ($allReservations as $reservation) {
            $ci     = $reservation->getCheckinDate();
            $co     = $reservation->getCheckoutDate();
            $propId = (string) $reservation->getProperty()?->getId();
            if (!$ci || !$co || !isset($propertySegments[$propId])) {
                continue;
            }
            if (in_array($reservation->getStatus(), ['cancelled', 'expired'], true)) {
                continue;
            }
            if ($co <= $today || $ci >= $windowEnd) {
                continue;
            }

            // Clamp to window
            $effectiveStart = $ci < $today ? $today : $ci;
            $effectiveEnd   = $co > $windowEnd ? $windowEnd : $co;
            $startCol = $dayIndex[$effectiveStart->format('Y-m-d')] ?? 0;
            $barDays  = (int) $effectiveStart->diff($effectiveEnd)->days;

            if ($barDays <= 0) {
                continue;
            }

            $guest        = $reservation->getGuest();
            $guestProfile = $guest?->getProfile();
            $guestName    = $guestProfile
                ? ($guestProfile->getFirstName() . ' ' . $guestProfile->getLastName())
                : ($guest?->getEmail() ?? '?');

            $propertySegments[$propId][] = [
                'startCol'  => $startCol,
                'barDays'   => $barDays,
                'status'    => $reservation->getStatus(),
                'guestName' => $guestName,
                'nights'    => (int) $ci->diff($co)->days,
                'id'        => (string) $reservation->getId(),
            ];
        }

        return $this->render('host/dashboard.html.twig', [
            'pendingReservations' => $pendingReservations,
            'allReservations'     => $allReservations,
            'properties'          => $properties,
            'timelineDays'        => $timelineDays,
            'propertySegments'    => $propertySegments,
        ]);
    }

    #[Route('/reservation/{id}/confirmer', name: 'confirm_booking', methods: ['POST'])]
    public function confirmBooking(Reservation $reservation, Request $request, BookingService $bookingService): Response
    {
        if (!$this->isCsrfTokenValid('host_confirm_' . $reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('host_dashboard');
        }

        $property = $reservation->getProperty();
        if ($property?->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        try {
            $bookingService->confirm($reservation);
            $this->addFlash('success', 'Réservation confirmée.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('host_dashboard');
    }

    #[Route('/reservation/{id}/refuser', name: 'reject_booking', methods: ['GET', 'POST'])]
    public function rejectBooking(Reservation $reservation, Request $request, BookingService $bookingService): Response
    {
        $property = $reservation->getProperty();
        if ($property?->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(BookingCancelType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->isCsrfTokenValid('host_reject_' . $reservation->getId(), $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('host_dashboard');
            }

            try {
                $preset = trim($request->request->getString('preset_reason'));
                $custom = trim((string) $form->get('cancellationReason')->getData());

                if ($preset === 'other' || $preset === '') {
                    $reason = $custom;
                } else {
                    $reason = $custom !== '' ? $preset . ' — ' . $custom : $preset;
                }

                $bookingService->reject($reservation, $reason);
                $this->addFlash('success', 'Réservation refusée.');
            } catch (\RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('host_dashboard');
        }

        return $this->render('host/reject.html.twig', [
            'reservation' => $reservation,
            'form'        => $form,
        ]);
    }
}
