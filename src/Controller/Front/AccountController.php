<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyBlockedPeriod;
use App\Entity\Reservation;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Form\AccountProfileType;
use App\Form\AccountSettingsType;
use App\Message\ReservationCancelledMessage;
use App\Message\ReservationConfirmedMessage;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\PropertyBlockedPeriodRepository;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte')]
#[IsGranted('ROLE_USER')]
final class AccountController extends AbstractController
{
    #[Route('/profil', name: 'app_account_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->getProfile() === null) {
            $user->setProfile(new UserProfile());
        }

        $form = $this->createForm(AccountProfileType::class, $user->getProfile());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('app_account_profile');
        }

        return $this->render('front/account/profile.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/parametres', name: 'app_account_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(AccountSettingsType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', 'Paramètres enregistrés.');

            return $this->redirectToRoute('app_account_settings');
        }

        return $this->render('front/account/settings.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/proprietes', name: 'app_account_properties', methods: ['GET'])]
    public function properties(
        PropertyRepository $propertyRepository,
        ReservationRepository $reservationRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/account/properties.html.twig', [
            'properties'          => $propertyRepository->findByHost($user),
            'pendingReservations' => $reservationRepository->findPendingForHost($user),
        ]);
    }

    #[Route('/reservation/{id}/confirmer', name: 'app_host_reservation_confirm', methods: ['POST'])]
    public function confirmReservation(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $reservation->getProperty()?->getHost() !== $user) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('confirm_reservation_' . $reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');

            return $this->redirectToRoute('app_account_properties');
        }

        if ($reservation->getStatus() === 'pending') {
            $reservation->setStatus('confirmed');
            $entityManager->flush();
            $bus->dispatch(new ReservationConfirmedMessage((string) $reservation->getId()));
            $this->addFlash('success', 'Réservation confirmée avec succès.');
        }

        return $this->redirectToRoute('app_account_properties');
    }

    #[Route('/reservation/{id}/refuser', name: 'app_host_reservation_reject', methods: ['POST'])]
    public function rejectReservation(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $reservation->getProperty()?->getHost() !== $user) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('reject_reservation_' . $reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');

            return $this->redirectToRoute('app_account_properties');
        }

        if ($reservation->getStatus() === 'pending') {
            $reservation->setStatus('cancelled');
            $reservation->setCancellationReason('Refusée par l\'hôte.');
            $entityManager->flush();
            $bus->dispatch(new ReservationCancelledMessage((string) $reservation->getId()));
            $this->addFlash('success', 'Demande de réservation refusée.');
        }

        return $this->redirectToRoute('app_account_properties');
    }

    #[Route('/proprietes/{id}/calendrier', name: 'app_account_property_calendar', methods: ['GET'])]
    public function propertyCalendar(
        Property $property,
        Request $request,
        ReservationRepository $reservationRepository,
        PropertyAvailabilityRepository $availabilityRepository,
        PropertyBlockedPeriodRepository $blockedPeriodRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        if ($property->getHost() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $year  = max(2020, min(2030, (int) ($request->query->get('year') ?? (int) date('Y'))));
        $month = max(1, min(12, (int) ($request->query->get('month') ?? (int) date('m'))));

        $reservations   = $reservationRepository->findForPropertyAndMonth($property, $year, $month);
        $availabilities = $availabilityRepository->findForPropertyAndMonth($property, $year, $month);
        $blockedPeriods = $blockedPeriodRepository->findForPropertyAndMonth($property, $year, $month);
        $allPeriods     = $blockedPeriodRepository->findAllForProperty($property);

        $availabilityMap = [];
        foreach ($availabilities as $avail) {
            $availabilityMap[$avail->getAvailableDate()->format('Y-m-d')] = $avail->isAvailable();
        }

        $reservationMap = [];
        foreach ($reservations as $reservation) {
            if (!in_array($reservation->getStatus(), ['confirmed', 'pending', 'completed'], true)) {
                continue;
            }
            $current  = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();
            while ($current < $checkout) {
                $key = $current->format('Y-m-d');
                if (!isset($reservationMap[$key]) || $reservation->getStatus() === 'confirmed') {
                    $reservationMap[$key] = $reservation;
                }
                $current = $current->modify('+1 day');
            }
        }

        $blockedPeriodMap = [];
        foreach ($blockedPeriods as $period) {
            $current = $period->getStartDate();
            $end     = $period->getEndDate();
            while ($current <= $end) {
                $key = $current->format('Y-m-d');
                if (!isset($blockedPeriodMap[$key])) {
                    $blockedPeriodMap[$key] = $period;
                }
                $current = $current->modify('+1 day');
            }
        }

        $firstDay       = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $daysInMonth    = (int) $firstDay->format('t');
        $firstDayOfWeek = (int) $firstDay->format('N');

        $daysData = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $key = sprintf('%04d-%02d-%02d', $year, $month, $day);
            if (isset($reservationMap[$key])) {
                $res = $reservationMap[$key];
                $daysData[$key] = [
                    'status'      => in_array($res->getStatus(), ['confirmed', 'completed'], true) ? 'reserved' : 'pending',
                    'reservation' => $res,
                    'period'      => null,
                ];
            } elseif (isset($blockedPeriodMap[$key])) {
                $daysData[$key] = [
                    'status'      => 'blocked_period',
                    'reservation' => null,
                    'period'      => $blockedPeriodMap[$key],
                ];
            } elseif (isset($availabilityMap[$key]) && $availabilityMap[$key] === false) {
                $daysData[$key] = ['status' => 'blocked', 'reservation' => null, 'period' => null];
            } else {
                $daysData[$key] = ['status' => 'available', 'reservation' => null, 'period' => null];
            }
        }

        $calendar = [];
        $week = array_fill(0, $firstDayOfWeek - 1, null);
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $key    = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $week[] = ['day' => $day, 'key' => $key, 'data' => $daysData[$key]];
            if (count($week) === 7) {
                $calendar[] = $week;
                $week = [];
            }
        }
        if (!empty($week)) {
            while (count($week) < 7) {
                $week[] = null;
            }
            $calendar[] = $week;
        }

        $prevMonth = $month === 1 ? 12 : $month - 1;
        $prevYear  = $month === 1 ? $year - 1 : $year;
        $nextMonth = $month === 12 ? 1 : $month + 1;
        $nextYear  = $month === 12 ? $year + 1 : $year;

        $frenchMonths = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

        return $this->render('front/account/calendar.html.twig', [
            'property'   => $property,
            'calendar'   => $calendar,
            'allPeriods' => $allPeriods,
            'year'       => $year,
            'month'      => $month,
            'monthLabel' => $frenchMonths[$month] . ' ' . $year,
            'prevYear'   => $prevYear,
            'prevMonth'  => $prevMonth,
            'nextYear'   => $nextYear,
            'nextMonth'  => $nextMonth,
            'today'      => (new \DateTimeImmutable())->format('Y-m-d'),
        ]);
    }

    #[Route('/proprietes/{id}/indisponibilite', name: 'app_host_blocked_period_create', methods: ['POST'])]
    public function createBlockedPeriod(
        Property $property,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        if ($property->getHost() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('blocked_period_' . $property->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');

            return $this->redirectToRoute('app_account_property_calendar', ['id' => $property->getId()]);
        }

        $startRaw = $request->request->get('start_date');
        $endRaw   = $request->request->get('end_date');
        $reason   = $request->request->get('reason', 'personal');
        $note     = trim((string) $request->request->get('note', ''));

        if (!$startRaw || !$endRaw) {
            $this->addFlash('error', 'Les dates de début et de fin sont obligatoires.');

            return $this->redirectToRoute('app_account_property_calendar', ['id' => $property->getId()]);
        }

        $start = \DateTimeImmutable::createFromFormat('Y-m-d', $startRaw);
        $end   = \DateTimeImmutable::createFromFormat('Y-m-d', $endRaw);

        if (!$start || !$end || $end < $start) {
            $this->addFlash('error', 'Les dates sont invalides. La date de fin doit être après la date de début.');

            return $this->redirectToRoute('app_account_property_calendar', ['id' => $property->getId()]);
        }

        if (!array_key_exists($reason, PropertyBlockedPeriod::REASONS)) {
            $reason = 'other';
        }

        $period = new PropertyBlockedPeriod();
        $period->setProperty($property);
        $period->setStartDate($start);
        $period->setEndDate($end);
        $period->setReason($reason);
        $period->setNote($note !== '' ? $note : null);
        $period->setCreatedBy($user);

        $entityManager->persist($period);
        $entityManager->flush();

        $this->addFlash('success', 'Période d\'indisponibilité déclarée avec succès.');

        return $this->redirectToRoute('app_account_property_calendar', [
            'id'    => $property->getId(),
            'year'  => (int) $start->format('Y'),
            'month' => (int) $start->format('m'),
        ]);
    }

    #[Route('/proprietes/{id}/indisponibilite/{pid}/supprimer', name: 'app_host_blocked_period_delete', methods: ['POST'])]
    public function deleteBlockedPeriod(
        Property $property,
        PropertyBlockedPeriod $pid,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        if ($property->getHost() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($pid->getProperty() !== $property) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('delete_period_' . $pid->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');

            return $this->redirectToRoute('app_account_property_calendar', ['id' => $property->getId()]);
        }

        $entityManager->remove($pid);
        $entityManager->flush();

        $this->addFlash('success', 'Période supprimée.');

        return $this->redirectToRoute('app_account_property_calendar', ['id' => $property->getId()]);
    }
}
