<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote')]
#[IsGranted('ROLE_USER')]
final class HostController extends AbstractController
{

    #[Route('/reservations', name: 'app_host_reservations', methods: ['GET'])]
    public function pendingReservations(
        ReservationRepository $reservationRepository,
        PropertyRepository $propertyRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/host/reservations.html.twig', [
            'pendingReservations' => $reservationRepository->findPendingByHost($user),
            'allReservations'     => $reservationRepository->findAllByHost($user),
            'properties'          => $propertyRepository->findBy(['host' => $user], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/reservations/{id}/accepter', name: 'app_host_reservation_confirm', methods: ['POST'])]
    public function confirmReservation(
        Reservation $reservation,
        ReservationService $reservationService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($reservation->getProperty()?->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation ne peut pas être confirmée.');

            return $this->redirectToRoute('app_host_reservations');
        }

        $reservationService->confirm($reservation, $user);
        $this->addFlash('success', 'Réservation confirmée. Le voyageur a été notifié.');

        return $this->redirectToRoute('app_host_reservations');
    }

    #[Route('/reservations/{id}/refuser', name: 'app_host_reservation_refuse', methods: ['POST'])]
    public function refuseReservation(
        Reservation $reservation,
        Request $request,
        ReservationService $reservationService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($reservation->getProperty()?->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation ne peut pas être refusée.');

            return $this->redirectToRoute('app_host_reservations');
        }

        $reason = trim((string) $request->request->get('reason', ''));
        if ($reason === '') {
            $this->addFlash('error', 'Un motif de refus est obligatoire.');

            return $this->redirectToRoute('app_host_reservations');
        }

        $reservationService->cancel($reservation, $user, $reason);
        $this->addFlash('success', 'Demande refusée. Le voyageur a été notifié.');

        return $this->redirectToRoute('app_host_reservations');
    }

    // ─── Property calendar management ─────────────────────────────────────────

    #[Route('/logements/{id}/calendrier', name: 'app_host_calendar', methods: ['GET'])]
    public function calendar(
        Property $property,
        Request $request,
        PropertyAvailabilityRepository $availabilityRepository,
        ReservationRepository $reservationRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $year  = (int) $request->query->get('year', (int) date('Y'));
        $month = (int) $request->query->get('month', (int) date('n'));

        if ($month < 1)  { $month = 12; --$year; }
        if ($month > 12) { $month = 1;  ++$year; }

        $blockedDates     = $availabilityRepository->findByPropertyAndMonth($property, $year, $month);
        $confirmedReservations = $reservationRepository->findConfirmedByProperty($property);

        $blockedMap = [];
        foreach ($blockedDates as $pa) {
            $blockedMap[$pa->getAvailableDate()?->format('Y-m-d')] = true;
        }

        return $this->render('front/host/calendar.html.twig', [
            'property'              => $property,
            'year'                  => $year,
            'month'                 => $month,
            'blockedMap'            => $blockedMap,
            'confirmedReservations' => $confirmedReservations,
        ]);
    }

    #[Route('/logements/{id}/bloquer', name: 'app_host_block_dates', methods: ['POST'])]
    public function blockDates(
        Property $property,
        Request $request,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $startRaw = $request->request->get('date_start');
        $endRaw   = $request->request->get('date_end');
        $action   = $request->request->get('action', 'block');

        $start = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $startRaw);
        $end   = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $endRaw);

        if ($start === false || $end === false || $start > $end) {
            $this->addFlash('error', 'Dates invalides.');

            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $current = $start;
        while ($current <= $end) {
            $existing = $availabilityRepository->findOneByPropertyAndDate($property, $current);

            if ($action === 'unblock') {
                if ($existing !== null) {
                    $em->remove($existing);
                }
            } else {
                if ($existing === null) {
                    $existing = new PropertyAvailability();
                    $existing->setProperty($property);
                    $existing->setAvailableDate($current);
                    $em->persist($existing);
                }
                $existing->setIsAvailable(false);
            }

            $current = $current->modify('+1 day');
        }

        $em->flush();

        $label = $action === 'unblock' ? 'débloquées' : 'bloquées';
        $this->addFlash('success', sprintf('Dates %s avec succès.', $label));

        return $this->redirectToRoute('app_host_calendar', [
            'id'    => $property->getId(),
            'year'  => $start->format('Y'),
            'month' => $start->format('n'),
        ]);
    }

    // ─── iCal token management ─────────────────────────────────────────────────

    #[Route('/logements/{id}/ical-token/regenerer', name: 'app_host_ical_token_regenerate', methods: ['POST'])]
    public function regenerateIcalToken(
        Property $property,
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $property->setIcalExportToken(bin2hex(random_bytes(32)));
        $em->flush();

        $this->addFlash('success', 'Token iCal régénéré.');

        return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
    }
}
