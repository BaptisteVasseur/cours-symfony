<?php

namespace App\Controller;

use App\Entity\BlockedPeriod;
use App\Entity\Booking;
use App\Entity\Listing;
use App\Form\BlockedPeriodType;
use App\Repository\BlockedPeriodRepository;
use App\Repository\BookingRepository;
use App\Service\BookingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host')]
#[IsGranted('ROLE_HOST')]
final class HostController extends AbstractController
{
    #[Route('/listings', name: 'host_listings', methods: ['GET'])]
    public function listings(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        return $this->render('host/listings.html.twig', [
            'listings' => $user->getListings(),
        ]);
    }

    #[Route('/bookings', name: 'host_bookings', methods: ['GET'])]
    public function bookings(BookingRepository $bookingRepo): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $pendingBookings   = $bookingRepo->findPendingForHost($user);
        $upcomingBookings  = $bookingRepo->findUpcomingConfirmedForHost($user);

        return $this->render('host/bookings.html.twig', [
            'pendingBookings'  => $pendingBookings,
            'upcomingBookings' => $upcomingBookings,
        ]);
    }

    #[Route('/booking/{id}/confirm', name: 'host_booking_confirm', methods: ['POST'])]
    public function confirmBooking(Booking $booking, Request $request, BookingService $bookingService): Response
    {
        $this->assertHostOwns($booking);

        if (!$this->isCsrfTokenValid('confirm_booking_' . $booking->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($booking->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation ne peut plus être confirmée.');
            return $this->redirectToRoute('host_bookings');
        }

        $bookingService->confirm($booking);
        $this->addFlash('success', 'Réservation confirmée.');

        return $this->redirectToRoute('host_bookings');
    }

    #[Route('/booking/{id}/refuse', name: 'host_booking_refuse', methods: ['POST'])]
    public function refuseBooking(Booking $booking, Request $request, BookingService $bookingService): Response
    {
        $this->assertHostOwns($booking);

        if (!$this->isCsrfTokenValid('refuse_booking_' . $booking->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $reason = trim($request->request->get('refuseReason', ''));
        if ($reason === '') {
            $this->addFlash('error', 'Un motif de refus est obligatoire.');
            return $this->redirectToRoute('host_bookings');
        }

        $bookingService->cancel($booking, $reason, 'host');
        $this->addFlash('success', 'Demande refusée.');

        return $this->redirectToRoute('host_bookings');
    }

    private function assertHostOwns(Booking $booking): void
    {
        if ($booking->getListing()->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
    }

    #[Route('/listings/{id}/calendar', name: 'host_calendar', methods: ['GET', 'POST'])]
    public function calendar(
        Listing $listing,
        Request $request,
        EntityManagerInterface $em,
        BookingRepository $bookingRepo,
        BlockedPeriodRepository $blockedRepo,
    ): Response {
        if ($listing->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Resolve displayed month
        $monthParam = $request->query->get('month');
        try {
            $firstDay = $monthParam
                ? new \DateTimeImmutable($monthParam . '-01')
                : new \DateTimeImmutable('first day of this month');
        } catch (\Exception) {
            $firstDay = new \DateTimeImmutable('first day of this month');
        }
        $firstDay = $firstDay->setTime(0, 0);

        $lastDay  = $firstDay->modify('last day of this month');
        $prevMonth = $firstDay->modify('-1 month')->format('Y-m');
        $nextMonth = $firstDay->modify('+1 month')->format('Y-m');

        // Build the form for a new blocked period
        $blockedPeriod = new BlockedPeriod();
        $form = $this->createForm(BlockedPeriodType::class, $blockedPeriod);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $start = $blockedPeriod->getStartDate();
            $end   = $blockedPeriod->getEndDate();

            if ($end <= $start) {
                $this->addFlash('error', 'La date de fin doit être postérieure à la date de début.');
            } else {
                $blockedPeriod->setListing($listing);
                $em->persist($blockedPeriod);
                $em->flush();
                $this->addFlash('success', 'Période bloquée ajoutée.');
                return $this->redirectToRoute('host_calendar', [
                    'id'    => $listing->getId(),
                    'month' => $firstDay->format('Y-m'),
                ]);
            }
        }

        // Fetch blocked periods and confirmed bookings for the month display
        $blockedPeriods = $blockedRepo->findForListingAndMonth($listing, $firstDay, $lastDay);
        $bookings       = $bookingRepo->findConfirmedForListingAndMonth($listing, $firstDay, $lastDay);

        // Build a set of "occupied" days for quick lookup in the template
        $occupiedDays = $this->buildDayMap($blockedPeriods, $bookings, $firstDay, $lastDay);

        // Calendar grid: pad to start on Monday
        $startPad = (int) $firstDay->format('N') - 1; // 0=Mon … 6=Sun
        $daysInMonth = (int) $lastDay->format('j');

        $monthNames = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];
        $monthLabel = $monthNames[(int) $firstDay->format('n')] . ' ' . $firstDay->format('Y');

        return $this->render('host/calendar.html.twig', [
            'listing'        => $listing,
            'form'           => $form,
            'firstDay'       => $firstDay,
            'monthLabel'     => $monthLabel,
            'prevMonth'      => $prevMonth,
            'nextMonth'      => $nextMonth,
            'startPad'       => $startPad,
            'daysInMonth'    => $daysInMonth,
            'occupiedDays'   => $occupiedDays,
            'blockedPeriods' => $blockedPeriods,
        ]);
    }

    #[Route('/blocked-period/{id}/delete', name: 'host_blocked_period_delete', methods: ['POST'])]
    public function deleteBlockedPeriod(
        BlockedPeriod $blockedPeriod,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if ($blockedPeriod->getListing()->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete_bp_' . $blockedPeriod->getId(), $request->request->get('_token'))) {
            $listingId = $blockedPeriod->getListing()->getId();
            $month     = $request->request->get('month', (new \DateTimeImmutable())->format('Y-m'));
            $em->remove($blockedPeriod);
            $em->flush();
            $this->addFlash('success', 'Période débloquée.');
            return $this->redirectToRoute('host_calendar', ['id' => $listingId, 'month' => $month]);
        }

        throw $this->createAccessDeniedException();
    }

    /**
     * Returns an array keyed by 'YYYY-MM-DD' with value 'blocked' or 'booking'.
     * 'booking' takes priority over 'blocked'.
     */
    private function buildDayMap(array $blockedPeriods, array $bookings, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $map = [];

        foreach ($blockedPeriods as $bp) {
            $cursor = \DateTimeImmutable::createFromMutable($bp->getStartDate());
            $end    = \DateTimeImmutable::createFromMutable($bp->getEndDate());
            while ($cursor < $end) {
                if ($cursor >= $from && $cursor <= $to) {
                    $map[$cursor->format('Y-m-d')] = 'blocked';
                }
                $cursor = $cursor->modify('+1 day');
            }
        }

        foreach ($bookings as $booking) {
            $cursor = \DateTimeImmutable::createFromMutable($booking->getStartDate());
            $end    = \DateTimeImmutable::createFromMutable($booking->getEndDate());
            while ($cursor < $end) {
                if ($cursor >= $from && $cursor <= $to) {
                    $map[$cursor->format('Y-m-d')] = 'booking';
                }
                $cursor = $cursor->modify('+1 day');
            }
        }

        return $map;
    }
}
