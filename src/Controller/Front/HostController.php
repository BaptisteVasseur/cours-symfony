<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Message\ReservationStatusChangedMessage;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/logements')]
#[IsGranted('ROLE_USER')]
final class HostController extends AbstractController
{
    #[Route('/{id}/gestion', name: 'app_host_manage', methods: ['GET'])]
    public function manage(
        Property $property,
        ReservationRepository $reservationRepository,
        PropertyAvailabilityRepository $availabilityRepository,
    ): Response {
        $this->denyAccessUnlessGranted('PROPERTY_EDIT', $property);

        $today = new \DateTimeImmutable('today');

        $occupiedDates = $availabilityRepository->findOccupiedDatesByProperty(
            $property,
            $today,
            $today->modify('+3 months'),
        );

        return $this->render('front/host/manage.html.twig', [
            'property' => $property,
            'calendarMonths' => $this->buildCalendarMonths($today, $occupiedDates),
            'pendingReservations' => $reservationRepository->findPendingByProperty($property),
            'upcomingReservations' => $reservationRepository->findConfirmedUpcomingByProperty($property, $today),
            'blocks' => $reservationRepository->findBlocksByProperty($property, $today),
            'cancelledReservations' => $reservationRepository->findCancelledByProperty($property),
        ]);
    }

    #[Route('/{id}/bloquer', name: 'app_host_block', methods: ['POST'])]
    public function block(
        Property $property,
        EntityManagerInterface $em,
        Request $request,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('PROPERTY_EDIT', $property);

        if (!$this->isCsrfTokenValid('block_' . $property->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');

            return $this->redirectToRoute('app_host_manage', ['id' => $property->getId()]);
        }

        $startDate = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->request->get('start_date'));
        $endDate = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->request->get('end_date'));

        if (!$startDate || !$endDate) {
            $this->addFlash('error', 'Les dates sont invalides.');

            return $this->redirectToRoute('app_host_manage', ['id' => $property->getId()]);
        }

        $startDate = $startDate->setTime(0, 0);
        $endDate = $endDate->setTime(0, 0);

        if ($endDate <= $startDate) {
            $this->addFlash('error', 'La date de fin doit être postérieure à la date de début.');

            return $this->redirectToRoute('app_host_manage', ['id' => $property->getId()]);
        }

        $reason = trim((string) $request->request->get('reason', '')) ?: null;

        $reservation = new Reservation();
        $reservation->setProperty($property);
        $reservation->setGuest($user);
        $reservation->setCheckinDate($startDate);
        $reservation->setCheckoutDate($endDate);
        $reservation->setGuestsCount(1);
        $reservation->setStatus('confirmed');
        $reservation->setType('block');
        $reservation->setTotalPrice('0.00');
        $reservation->setCurrency('EUR');
        $reservation->setBlockReason($reason);
        $em->persist($reservation);

        $current = $startDate;
        while ($current <= $endDate) {
            $availability = new PropertyAvailability();
            $availability->setProperty($property);
            $availability->setReservation($reservation);
            $availability->setOccupiedDate($current);
            $em->persist($availability);
            $current = $current->modify('+1 day');
        }

        $em->flush();
        $this->addFlash('success', sprintf(
            'Logement bloqué du %s au %s.',
            $startDate->format('d/m/Y'),
            $endDate->format('d/m/Y'),
        ));

        return $this->redirectToRoute('app_host_manage', ['id' => $property->getId()]);
    }

    #[Route('/reservations/{id}/accepter', name: 'app_host_reservation_accept', methods: ['POST'])]
    public function accept(
        Reservation $reservation,
        EntityManagerInterface $em,
        Request $request,
        MessageBusInterface $bus,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $property = $reservation->getProperty();
        $this->denyAccessUnlessGranted('PROPERTY_EDIT', $property);

        if (!$this->isCsrfTokenValid('accept_' . $reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');

            return $this->redirectToRoute('app_host_manage', ['id' => $property->getId()]);
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation ne peut pas être acceptée.');

            return $this->redirectToRoute('app_host_manage', ['id' => $property->getId()]);
        }

        $reservation->setStatus('confirmed');

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus('pending');
        $history->setNewStatus('confirmed');
        $history->setChangedBy($user);
        $em->persist($history);

        $current = $reservation->getCheckinDate();
        $end = $reservation->getCheckoutDate();
        while ($current < $end) {
            $availability = new PropertyAvailability();
            $availability->setProperty($property);
            $availability->setReservation($reservation);
            $availability->setOccupiedDate($current);
            $em->persist($availability);
            $current = $current->modify('+1 day');
        }

        $em->flush();

        $bus->dispatch(new ReservationStatusChangedMessage((string) $reservation->getId(), 'confirmed'));

        $this->addFlash('success', 'Réservation acceptée avec succès.');

        return $this->redirectToRoute('app_host_manage', ['id' => $property->getId()]);
    }

    #[Route('/{id}/calendar-token/generer', name: 'app_host_calendar_token_generate', methods: ['POST'])]
    public function generateCalendarToken(
        Property $property,
        EntityManagerInterface $em,
        Request $request,
    ): Response {
        $this->denyAccessUnlessGranted('PROPERTY_EDIT', $property);

        if (!$this->isCsrfTokenValid('calendar_token_' . $property->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');

            return $this->redirectToRoute('app_host_manage', ['id' => $property->getId()]);
        }

        $property->setCalendarToken(bin2hex(random_bytes(32)));
        $em->flush();

        $this->addFlash('success', 'Lien iCal généré avec succès.');

        return $this->redirectToRoute('app_host_manage', ['id' => $property->getId()]);
    }

    #[Route('/{id}/calendar-token/revoquer', name: 'app_host_calendar_token_revoke', methods: ['POST'])]
    public function revokeCalendarToken(
        Property $property,
        EntityManagerInterface $em,
        Request $request,
    ): Response {
        $this->denyAccessUnlessGranted('PROPERTY_EDIT', $property);

        if (!$this->isCsrfTokenValid('calendar_token_' . $property->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');

            return $this->redirectToRoute('app_host_manage', ['id' => $property->getId()]);
        }

        $property->setCalendarToken(null);
        $em->flush();

        $this->addFlash('success', 'Lien iCal révoqué.');

        return $this->redirectToRoute('app_host_manage', ['id' => $property->getId()]);
    }

    #[Route('/reservations/{id}/refuser', name: 'app_host_reservation_refuse', methods: ['POST'])]
    public function refuse(
        Reservation $reservation,
        EntityManagerInterface $em,
        Request $request,
        MessageBusInterface $bus,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $property = $reservation->getProperty();
        $this->denyAccessUnlessGranted('PROPERTY_EDIT', $property);

        if (!$this->isCsrfTokenValid('refuse_' . $reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');

            return $this->redirectToRoute('app_host_manage', ['id' => $property->getId()]);
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation ne peut pas être refusée.');

            return $this->redirectToRoute('app_host_manage', ['id' => $property->getId()]);
        }

        $reason = trim((string) $request->request->get('reason', ''));
        if ($reason === '') {
            $this->addFlash('error', 'Le motif du refus est obligatoire.');

            return $this->redirectToRoute('app_host_manage', ['id' => $property->getId()]);
        }

        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus('pending');
        $history->setNewStatus('cancelled');
        $history->setChangedBy($user);
        $em->persist($history);

        $em->createQuery('DELETE FROM App\Entity\PropertyAvailability a WHERE a.reservation = :reservation')
            ->setParameter('reservation', $reservation)
            ->execute();

        $em->flush();

        $bus->dispatch(new ReservationStatusChangedMessage((string) $reservation->getId(), 'cancelled'));

        $this->addFlash('success', 'Réservation refusée.');

        return $this->redirectToRoute('app_host_manage', ['id' => $property->getId()]);
    }

    /**
     * @param string[] $occupiedDates
     * @return list<array{label: string, weeks: list<list<array{day: int, dateStr: string, isCurrentMonth: bool, isToday: bool, isPast: bool, isOccupied: bool}>>}>
     */
    private function buildCalendarMonths(\DateTimeImmutable $today, array $occupiedDates): array
    {
        $frenchMonths = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];

        $occupiedSet = array_flip($occupiedDates);
        $todayStr = $today->format('Y-m-d');
        $months = [];

        for ($m = 0; $m < 3; $m++) {
            $monthStart = $today->modify("first day of +{$m} months")->setTime(0, 0);
            $monthEnd = $today->modify("last day of +{$m} months")->setTime(0, 0);

            $dayOfWeek = (int) $monthStart->format('N');
            $calStart = $monthStart->modify('-' . ($dayOfWeek - 1) . ' days');

            $dayOfWeekEnd = (int) $monthEnd->format('N');
            $calEnd = $dayOfWeekEnd < 7
                ? $monthEnd->modify('+' . (7 - $dayOfWeekEnd) . ' days')
                : $monthEnd;

            $weeks = [];
            $week = [];
            $current = $calStart;

            while ($current <= $calEnd) {
                $dateStr = $current->format('Y-m-d');
                $week[] = [
                    'day' => (int) $current->format('j'),
                    'dateStr' => $dateStr,
                    'isCurrentMonth' => $current->format('Y-m') === $monthStart->format('Y-m'),
                    'isToday' => $dateStr === $todayStr,
                    'isPast' => $current < $today,
                    'isOccupied' => isset($occupiedSet[$dateStr]),
                ];

                if (count($week) === 7) {
                    $weeks[] = $week;
                    $week = [];
                }

                $current = $current->modify('+1 day');
            }

            $months[] = [
                'label' => $frenchMonths[(int) $monthStart->format('n')] . ' ' . $monthStart->format('Y'),
                'weeks' => $weeks,
            ];
        }

        return $months;
    }
}
