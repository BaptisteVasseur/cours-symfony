<?php

namespace App\Controller;

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

    #[Route('/bookings/{id}/accept', name: 'host_booking_accept', methods: ['POST'])]
    public function accept(
        \App\Entity\Booking $booking,
        Request $request,
        BookingService $bookingService,
    ): Response {
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
    public function reject(
        \App\Entity\Booking $booking,
        Request $request,
        BookingService $bookingService,
    ): Response {
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

    #[Route('/properties/{id}/calendar', name: 'host_property_calendar')]
    public function calendar(
        Property $property,
        Request $request,
        AvailabilityService $availabilityService,
        PropertyAvailabilityRepository $availabilityRepo,
        EntityManagerInterface $em,
    ): Response {
        /** @var \App\Entity\User $host */
        $host = $this->getUser();

        if ($property->getHost() !== $host) {
            throw $this->createAccessDeniedException('Ce logement ne vous appartient pas.');
        }

        $monthStr = $request->query->get('month', (new \DateTimeImmutable())->format('Y-m'));
        [$year, $month] = array_map('intval', explode('-', $monthStr));
        $year  = max(2020, min(2030, $year));
        $month = max(1, min(12, $month));

        $prevMonth = \DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $year, $month))->modify('-1 month');
        $nextMonth = \DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $year, $month))->modify('+1 month');

        $calendarStates = $availabilityService->getCalendarStates($property, $year, $month);
        $firstDayOfWeek = (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('N');

        $blockedPeriods = $availabilityRepo->findForProperty($property);

        $availability = new PropertyAvailability();
        $availability->setProperty($property);
        $blockForm = $this->createForm(BlockAvailabilityType::class, $availability);
        $blockForm->handleRequest($request);

        if ($blockForm->isSubmitted() && $blockForm->isValid()) {
            if ($availability->getEndDate() <= $availability->getStartDate()) {
                $this->addFlash('error', 'La date de fin doit être après la date de début.');
            } else {
                $em->persist($availability);
                $em->flush();
                $this->addFlash('success', 'Période bloquée ajoutée.');
                return $this->redirectToRoute('host_property_calendar', ['id' => $property->getId(), 'month' => $monthStr]);
            }
        }

        // Handle instant booking toggle
        if ($request->isMethod('POST') && $request->request->has('toggle_instant')) {
            if ($this->isCsrfTokenValid('toggle_instant_' . $property->getId(), $request->request->get('_token_instant'))) {
                $property->setInstantBooking(!$property->isInstantBooking());
                $em->flush();
                $this->addFlash('success', 'Mode de réservation mis à jour.');
                return $this->redirectToRoute('host_property_calendar', ['id' => $property->getId(), 'month' => $monthStr]);
            }
        }

        // Handle calendar token generation/revocation
        if ($request->isMethod('POST') && $request->request->has('gen_token')) {
            if ($this->isCsrfTokenValid('gen_token_' . $property->getId(), $request->request->get('_token_cal'))) {
                $property->generateCalendarToken();
                $em->flush();
                $this->addFlash('success', 'Token iCal généré.');
                return $this->redirectToRoute('host_property_calendar', ['id' => $property->getId(), 'month' => $monthStr]);
            }
        }

        if ($request->isMethod('POST') && $request->request->has('revoke_token')) {
            if ($this->isCsrfTokenValid('revoke_token_' . $property->getId(), $request->request->get('_token_rev'))) {
                $property->revokeCalendarToken();
                $em->flush();
                $this->addFlash('success', 'Token iCal révoqué.');
                return $this->redirectToRoute('host_property_calendar', ['id' => $property->getId(), 'month' => $monthStr]);
            }
        }

        return $this->render('host/calendar.html.twig', [
            'property'        => $property,
            'calendarStates'  => $calendarStates,
            'firstDayOfWeek'  => $firstDayOfWeek,
            'year'            => $year,
            'month'           => $month,
            'prevMonth'       => $prevMonth->format('Y-m'),
            'nextMonth'       => $nextMonth->format('Y-m'),
            'monthLabel'      => (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('F Y'),
            'blockedPeriods'  => $blockedPeriods,
            'blockForm'       => $blockForm,
        ]);
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
        }

        return $this->redirectToRoute('host_property_calendar', [
            'id' => $availability->getProperty()->getId(),
        ]);
    }
}
