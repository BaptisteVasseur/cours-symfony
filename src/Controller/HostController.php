<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Form\BlockAvailabilityType;
use App\Repository\BookingRepository;
use App\Repository\PropertyAvailabilityRepository;
use App\Service\AvailabilityService;
use App\Service\BookingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host')]
#[IsGranted('ROLE_HOST')]
class HostController extends AbstractController
{
    // ── Dashboard ─────────────────────────────────────────────────────────────

    #[Route('/dashboard', name: 'host_dashboard')]
    public function dashboard(BookingRepository $bookingRepo): Response
    {
        /** @var \App\Entity\User $host */
        $host = $this->getUser();

        return $this->render('host/dashboard.html.twig', [
            'pendingBookings' => $bookingRepo->findPendingForHost($host),
            'allBookings'     => $bookingRepo->findForHost($host),
        ]);
    }

    // ── Modération des demandes ────────────────────────────────────────────────

    #[Route('/bookings/{id}/accept', name: 'host_booking_accept', methods: ['POST'])]
    public function accept(Booking $booking, Request $request, BookingService $bookingService): Response
    {
        if (!$this->isCsrfTokenValid('accept_booking_' . $booking->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('host_dashboard');
        }

        try {
            $bookingService->confirm($booking, $this->getUser());
            $this->addFlash('success', 'Réservation confirmée. Le voyageur a été notifié.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('host_dashboard');
    }

    #[Route('/bookings/{id}/reject', name: 'host_booking_reject', methods: ['POST'])]
    public function reject(Booking $booking, Request $request, BookingService $bookingService): Response
    {
        if (!$this->isCsrfTokenValid('reject_booking_' . $booking->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('host_dashboard');
        }

        $reason = trim((string) $request->request->get('reason', ''));
        if (!$reason) {
            $this->addFlash('error', 'Veuillez indiquer un motif de refus.');
            return $this->redirectToRoute('host_dashboard');
        }

        try {
            $bookingService->cancel($booking, $this->getUser(), $reason);
            $this->addFlash('success', 'Demande refusée. Le voyageur a été notifié.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('host_dashboard');
    }

    // ── Calendrier (lecture seule) ─────────────────────────────────────────────

    #[Route('/properties/{id}/calendar', name: 'host_property_calendar', methods: ['GET'])]
    public function calendar(
        Property $property,
        Request $request,
        AvailabilityService $availabilityService,
        PropertyAvailabilityRepository $availabilityRepo,
    ): Response {
        $this->assertIsOwner($property);

        $monthStr = $request->query->get('month', (new \DateTimeImmutable())->format('Y-m'));
        [$year, $month] = array_map('intval', explode('-', $monthStr));
        $year  = max(2020, min(2030, $year));
        $month = max(1, min(12, $month));

        $base      = \DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $year, $month));
        $prevMonth = $base->modify('-1 month')->format('Y-m');
        $nextMonth = $base->modify('+1 month')->format('Y-m');

        $blockForm = $this->createForm(BlockAvailabilityType::class, new PropertyAvailability(), [
            'action' => $this->generateUrl('host_availability_create', ['id' => $property->getId()]),
            'method' => 'POST',
        ]);

        return $this->render('host/calendar.html.twig', [
            'property'       => $property,
            'calendarStates' => $availabilityService->getCalendarStates($property, $year, $month),
            'firstDayOfWeek' => (int) $base->format('N'),
            'year'           => $year,
            'month'          => $month,
            'prevMonth'      => $prevMonth,
            'nextMonth'      => $nextMonth,
            'monthLabel'     => $base->format('F Y'),
            'blockedPeriods' => $availabilityRepo->findForProperty($property),
            'blockForm'      => $blockForm,
        ]);
    }

    // ── Gestion des périodes bloquées ──────────────────────────────────────────

    #[Route('/properties/{id}/availabilities', name: 'host_availability_create', methods: ['POST'])]
    public function blockAvailability(
        Property $property,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->assertIsOwner($property);

        $availability = new PropertyAvailability();
        $availability->setProperty($property);

        $form = $this->createForm(BlockAvailabilityType::class, $availability);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Formulaire invalide. Vérifiez les dates.');
            return $this->redirectToCalendar($property->getId());
        }

        if ($availability->getEndDate() <= $availability->getStartDate()) {
            $this->addFlash('error', 'La date de fin doit être après la date de début.');
            return $this->redirectToCalendar($property->getId());
        }

        $em->persist($availability);
        $em->flush();
        $this->addFlash('success', 'Période bloquée ajoutée.');

        return $this->redirectToCalendar($property->getId());
    }

    #[Route('/availabilities/{id}/delete', name: 'host_availability_delete', methods: ['POST'])]
    public function deleteAvailability(
        PropertyAvailability $availability,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        /** @var \App\Entity\User $host */
        $host = $this->getUser();

        if ($availability->getProperty()->getHost() !== $host) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('del_avail_' . $availability->getId(), $request->request->get('_token'))) {
            $propertyId = $availability->getProperty()->getId();
            $em->remove($availability);
            $em->flush();
            $this->addFlash('success', 'Période supprimée.');

            return $this->redirectToCalendar($propertyId);
        }

        return $this->redirectToRoute('host_dashboard');
    }

    // ── Réservation instantanée ────────────────────────────────────────────────

    #[Route('/properties/{id}/instant-booking/toggle', name: 'host_instant_booking_toggle', methods: ['POST'])]
    public function toggleInstantBooking(
        Property $property,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->assertIsOwner($property);

        if (!$this->isCsrfTokenValid('toggle_instant_' . $property->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToCalendar($property->getId());
        }

        $property->setInstantBooking(!$property->isInstantBooking());
        $em->flush();
        $this->addFlash('success', 'Mode de réservation mis à jour.');

        return $this->redirectToCalendar($property->getId());
    }

    // ── Token iCal ────────────────────────────────────────────────────────────

    #[Route('/properties/{id}/calendar-token/generate', name: 'host_calendar_token_generate', methods: ['POST'])]
    public function generateCalendarToken(
        Property $property,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->assertIsOwner($property);

        if (!$this->isCsrfTokenValid('gen_token_' . $property->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToCalendar($property->getId());
        }

        $property->generateCalendarToken();
        $em->flush();
        $this->addFlash('success', 'Token iCal généré.');

        return $this->redirectToCalendar($property->getId());
    }

    #[Route('/properties/{id}/calendar-token/revoke', name: 'host_calendar_token_revoke', methods: ['POST'])]
    public function revokeCalendarToken(
        Property $property,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->assertIsOwner($property);

        if (!$this->isCsrfTokenValid('revoke_token_' . $property->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToCalendar($property->getId());
        }

        $property->revokeCalendarToken();
        $em->flush();
        $this->addFlash('success', 'Token iCal révoqué.');

        return $this->redirectToCalendar($property->getId());
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    private function assertIsOwner(Property $property): void
    {
        if ($property->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Ce logement ne vous appartient pas.');
        }
    }

    private function redirectToCalendar(mixed $propertyId): Response
    {
        return $this->redirectToRoute('host_property_calendar', ['id' => $propertyId]);
    }
}
