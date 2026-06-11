<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Service\AvailabilityManager;
use App\Service\BookingException;
use App\Service\BookingManager;
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
    #[Route('/properties', name: 'app_host_property_index', methods: ['GET'])]
    public function properties(PropertyRepository $propertyRepository): Response
    {
        return $this->render('host/properties.html.twig', [
            'properties' => $propertyRepository->findByHost($this->currentHost()),
        ]);
    }

    #[Route('/reservations', name: 'app_host_reservation_index', methods: ['GET'])]
    public function reservations(ReservationRepository $reservationRepository): Response
    {
        $host = $this->currentHost();

        return $this->render('host/reservations.html.twig', [
            'pending' => $reservationRepository->findPendingForHost($host),
            'reservations' => $reservationRepository->findForHost($host),
        ]);
    }

    #[Route('/reservations/{id}/accept', name: 'app_host_reservation_accept', methods: ['POST'])]
    public function accept(Reservation $reservation, Request $request, BookingManager $bookingManager): Response
    {
        $this->denyUnlessReservationOwner($reservation);

        if ($this->isCsrfTokenValid('accept'.$reservation->getId(), $request->getPayload()->getString('_token'))) {
            try {
                $bookingManager->confirm($reservation, $this->currentHost());
                $this->addFlash('success', 'Réservation acceptée. Le voyageur a été notifié.');
            } catch (BookingException $exception) {
                $this->addFlash('danger', $exception->getMessage());
            }
        }

        return $this->redirectToRoute('app_host_reservation_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/reservations/{id}/reject', name: 'app_host_reservation_reject', methods: ['POST'])]
    public function reject(Reservation $reservation, Request $request, BookingManager $bookingManager): Response
    {
        $this->denyUnlessReservationOwner($reservation);

        if ($this->isCsrfTokenValid('reject'.$reservation->getId(), $request->getPayload()->getString('_token'))) {
            $reason = trim($request->getPayload()->getString('reason'));
            try {
                $bookingManager->reject($reservation, $this->currentHost(), $reason);
                $this->addFlash('success', 'Demande refusée. Le voyageur a été notifié.');
            } catch (BookingException $exception) {
                $this->addFlash('danger', $exception->getMessage());
            }
        }

        return $this->redirectToRoute('app_host_reservation_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/reservations/{id}/cancel', name: 'app_host_reservation_cancel', methods: ['POST'])]
    public function cancelReservation(Reservation $reservation, Request $request, BookingManager $bookingManager): Response
    {
        $this->denyUnlessReservationOwner($reservation);

        if ($this->isCsrfTokenValid('hostcancel'.$reservation->getId(), $request->getPayload()->getString('_token'))) {
            $reason = trim($request->getPayload()->getString('reason'));
            try {
                $bookingManager->cancel($reservation, $this->currentHost(), $reason);
                $this->addFlash('success', 'Réservation annulée. Les deux parties ont été notifiées.');
            } catch (BookingException $exception) {
                $this->addFlash('danger', $exception->getMessage());
            }
        }

        return $this->redirectToRoute('app_host_reservation_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/properties/{id}/calendar', name: 'app_host_calendar', methods: ['GET'])]
    public function calendar(
        Property $property,
        Request $request,
        PropertyAvailabilityRepository $availabilityRepository,
        ReservationRepository $reservationRepository,
    ): Response {
        $this->denyUnlessOwner($property);

        $today = new \DateTimeImmutable('today');
        $year = $request->query->getInt('year', (int) $today->format('Y'));
        $month = $request->query->getInt('month', (int) $today->format('n'));

        $firstDay = \DateTimeImmutable::createFromFormat('!Y-n-j', sprintf('%d-%d-1', $year, $month))
            ?: $today->modify('first day of this month');
        $lastDay = $firstDay->modify('last day of this month');

        $blockedDates = [];
        foreach ($availabilityRepository->findBlockedInRange($property, $firstDay, $lastDay) as $row) {
            $blockedDates[$row->getAvailableDate()->format('Y-m-d')] = $row->getSource();
        }

        $reservedDates = [];
        foreach ($reservationRepository->findConfirmedInRange($property, $firstDay, $lastDay->modify('+1 day')) as $reservation) {
            $cursor = $reservation->getCheckinDate();
            $end = $reservation->getCheckoutDate();
            while ($cursor < $end) {
                $reservedDates[$cursor->format('Y-m-d')] = true;
                $cursor = $cursor->modify('+1 day');
            }
        }

        $leadingBlanks = (int) $firstDay->format('N') - 1;
        $cells = array_fill(0, $leadingBlanks, null);

        for ($day = 1; $day <= (int) $firstDay->format('t'); ++$day) {
            $date = $firstDay->setDate($year, $month, $day);
            $key = $date->format('Y-m-d');

            if (isset($reservedDates[$key])) {
                $status = 'reserved';
            } elseif (isset($blockedDates[$key])) {
                $status = $blockedDates[$key] === 'ical_import' ? 'imported' : 'blocked';
            } elseif ($date < $today) {
                $status = 'past';
            } else {
                $status = 'available';
            }

            $cells[] = ['day' => $day, 'date' => $key, 'status' => $status];
        }

        return $this->render('host/calendar.html.twig', [
            'property' => $property,
            'cells' => $cells,
            'year' => $year,
            'month' => $month,
            'monthLabel' => $firstDay->format('F Y'),
            'prev' => $firstDay->modify('-1 month'),
            'next' => $firstDay->modify('+1 month'),
        ]);
    }

    #[Route('/properties/{id}/availability/block', name: 'app_host_availability_block', methods: ['POST'])]
    public function block(Property $property, Request $request, AvailabilityManager $availabilityManager): Response
    {
        $this->denyUnlessOwner($property);

        if (!$this->isCsrfTokenValid('block'.$property->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToCalendar($property);
        }

        [$from, $to] = $this->readRange($request);
        if ($from === null || $to === null) {
            $this->addFlash('danger', 'Veuillez renseigner une période valide.');

            return $this->redirectToCalendar($property);
        }

        $blocked = $availabilityManager->block($property, $from, $to);
        $this->addFlash('success', sprintf('%d journée(s) bloquée(s).', $blocked));

        return $this->redirectToCalendar($property, $from);
    }

    #[Route('/properties/{id}/availability/unblock', name: 'app_host_availability_unblock', methods: ['POST'])]
    public function unblock(Property $property, Request $request, AvailabilityManager $availabilityManager): Response
    {
        $this->denyUnlessOwner($property);

        if (!$this->isCsrfTokenValid('unblock'.$property->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToCalendar($property);
        }

        [$from, $to] = $this->readRange($request);
        if ($from === null || $to === null) {
            $this->addFlash('danger', 'Veuillez renseigner une période valide.');

            return $this->redirectToCalendar($property);
        }

        $freed = $availabilityManager->unblock($property, $from, $to);
        $this->addFlash('success', sprintf('%d journée(s) libérée(s).', $freed));

        return $this->redirectToCalendar($property, $from);
    }

    /**
     * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable}
     */
    private function readRange(Request $request): array
    {
        $payload = $request->getPayload();
        $from = \DateTimeImmutable::createFromFormat('!Y-m-d', $payload->getString('from')) ?: null;
        $to = \DateTimeImmutable::createFromFormat('!Y-m-d', $payload->getString('to')) ?: null;

        return [$from, $to];
    }

    private function redirectToCalendar(Property $property, ?\DateTimeImmutable $month = null): Response
    {
        $params = ['id' => $property->getId()];
        if ($month !== null) {
            $params['year'] = (int) $month->format('Y');
            $params['month'] = (int) $month->format('n');
        }

        return $this->redirectToRoute('app_host_calendar', $params, Response::HTTP_SEE_OTHER);
    }

    #[Route('/properties/{id}/ical/regenerate', name: 'app_host_ical_regenerate', methods: ['POST'])]
    public function regenerateIcalToken(Property $property, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyUnlessOwner($property);

        if ($this->isCsrfTokenValid('ical'.$property->getId(), $request->getPayload()->getString('_token'))) {
            $property->setIcalExportToken(bin2hex(random_bytes(32)));
            $em->flush();
            $this->addFlash('success', 'Lien de synchronisation iCal régénéré. L\'ancien lien est désormais invalide.');
        }

        return $this->redirectToCalendar($property);
    }

    private function denyUnlessOwner(Property $property): void
    {
        if ($property->getHost() !== $this->currentHost()) {
            throw $this->createAccessDeniedException('Ce logement ne vous appartient pas.');
        }
    }

    private function denyUnlessReservationOwner(Reservation $reservation): void
    {
        if ($reservation->getProperty()?->getHost() !== $this->currentHost()) {
            throw $this->createAccessDeniedException('Cette réservation ne concerne pas vos logements.');
        }
    }

    private function currentHost(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
