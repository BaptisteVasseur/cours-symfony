<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\User;
use App\Repository\PropertyRepository;
use App\Service\AvailabilityService;
use App\Service\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote/logement')]
#[IsGranted('ROLE_HOST')]
final class HostCalendarController extends AbstractController
{
    #[Route('/{id}/calendrier', name: 'app_host_calendar', methods: ['GET'])]
    public function calendar(
        Property $property,
        Request $request,
        AvailabilityService $availabilityService,
    ): Response {
        $this->denyAccessUnlessGranted('PROPERTY_MANAGE', $property);

        $year = $request->query->getInt('year', (int) date('Y'));
        $month = $request->query->getInt('month', (int) date('n'));

        if ($month < 1 || $month > 12) {
            $month = (int) date('n');
        }

        $blockedDates = $availabilityService->getBlockedDatesForMonth($property, $year, $month);
        $confirmedDates = $availabilityService->getConfirmedReservationDatesForMonth($property, $year, $month);

        return $this->render('front/host/calendar.html.twig', [
            'property' => $property,
            'year' => $year,
            'month' => $month,
            'blockedDates' => $blockedDates,
            'confirmedDates' => $confirmedDates,
        ]);
    }

    #[Route('/{id}/calendrier/bloquer', name: 'app_host_calendar_block', methods: ['POST'])]
    public function block(
        Property $property,
        Request $request,
        AvailabilityService $availabilityService,
    ): Response {
        $this->denyAccessUnlessGranted('PROPERTY_MANAGE', $property);

        if (!$this->isCsrfTokenValid('host_calendar_'.$property->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $from = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->request->get('from'));
        $to = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->request->get('to'));

        if ($from === false || $to === false || $from >= $to) {
            $this->addFlash('error', 'Dates invalides.');

            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $availabilityService->blockDates($property, $from, $to);
        $this->addFlash('success', 'Dates bloquées avec succès.');

        return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
    }

    #[Route('/{id}/calendrier/debloquer', name: 'app_host_calendar_unblock', methods: ['POST'])]
    public function unblock(
        Property $property,
        Request $request,
        AvailabilityService $availabilityService,
    ): Response {
        $this->denyAccessUnlessGranted('PROPERTY_MANAGE', $property);

        if (!$this->isCsrfTokenValid('host_calendar_'.$property->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $from = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->request->get('from'));
        $to = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->request->get('to'));

        if ($from === false || $to === false || $from >= $to) {
            $this->addFlash('error', 'Dates invalides.');

            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $availabilityService->unblockDates($property, $from, $to);
        $this->addFlash('success', 'Dates débloquées avec succès.');

        return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
    }

    #[Route('/{id}/demandes', name: 'app_host_reservations', methods: ['GET'])]
    public function pendingRequests(
        Property $property,
        PropertyRepository $propertyRepository,
    ): Response {
        $this->denyAccessUnlessGranted('PROPERTY_MANAGE', $property);

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/host/pending_requests.html.twig', [
            'property' => $property,
        ]);
    }

    #[Route('/demandes', name: 'app_host_all_requests', methods: ['GET'])]
    public function allPendingRequests(
        \App\Repository\ReservationRepository $reservationRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/host/dashboard.html.twig', [
            'pendingReservations' => $reservationRepository->findPendingForHost($user),
            'confirmedReservations' => $reservationRepository->findConfirmedUpcomingForHost($user),
            'toCompleteReservations' => $reservationRepository->findConfirmedPassedForHost($user),
        ]);
    }

    #[Route('/reservation/{id}/confirmer', name: 'app_host_reservation_confirm', methods: ['POST'])]
    public function confirmReservation(
        \App\Entity\Reservation $reservation,
        Request $request,
        ReservationService $reservationService,
    ): Response {
        $this->denyAccessUnlessGranted('RESERVATION_MANAGE', $reservation);

        if (!$this->isCsrfTokenValid('confirm_reservation_'.$reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_host_all_requests');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $reservationService->confirm($reservation, $user);
            $this->addFlash('success', 'Réservation confirmée.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_host_all_requests');
    }

    #[Route('/reservation/{id}/refuser', name: 'app_host_reservation_refuse', methods: ['POST'])]
    public function refuseReservation(
        \App\Entity\Reservation $reservation,
        Request $request,
        ReservationService $reservationService,
    ): Response {
        $this->denyAccessUnlessGranted('RESERVATION_MANAGE', $reservation);

        if (!$this->isCsrfTokenValid('refuse_reservation_'.$reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_host_all_requests');
        }

        $reason = trim((string) $request->request->get('reason', ''));
        if ($reason === '') {
            $this->addFlash('error', 'Le motif de refus est obligatoire.');

            return $this->redirectToRoute('app_host_all_requests');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $reservationService->cancel($reservation, $user, $reason);
            $this->addFlash('success', 'Demande refusée.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_host_all_requests');
    }

    #[Route('/reservation/{id}/terminer', name: 'app_host_reservation_complete', methods: ['POST'])]
    public function completeReservation(
        \App\Entity\Reservation $reservation,
        Request $request,
        ReservationService $reservationService,
    ): Response {
        $this->denyAccessUnlessGranted('RESERVATION_MANAGE', $reservation);

        if (!$this->isCsrfTokenValid('complete_reservation_'.$reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_host_all_requests');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $reservationService->complete($reservation, $user);
            $this->addFlash('success', 'Séjour marqué comme terminé.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_host_all_requests');
    }

    #[Route('/{id}/ical/regenerer', name: 'app_host_ical_regenerate', methods: ['POST'])]
    public function regenerateIcalToken(
        Property $property,
        Request $request,
        \Doctrine\ORM\EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted('PROPERTY_MANAGE', $property);

        if (!$this->isCsrfTokenValid('ical_token_'.$property->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $property->regenerateIcalToken();
        $entityManager->flush();
        $this->addFlash('success', 'Token iCal régénéré. Mettez à jour vos abonnements.');

        return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
    }
}
