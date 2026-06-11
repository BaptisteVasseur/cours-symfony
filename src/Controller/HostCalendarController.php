<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Entity\User;
use App\Form\HostAvailabilityBlockType;
use App\Form\HostICalSyncType;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\PropertyICalSyncRepository;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Service\Booking\PropertyAvailabilityManager;
use App\Service\ICal\ICalSyncService;
use App\Service\ICal\PropertyICalManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host')]
#[IsGranted('ROLE_HOST')]
final class HostCalendarController extends AbstractController
{
    #[Route('/calendar', name: 'app_host_calendar_index', methods: ['GET'])]
    public function index(PropertyRepository $propertyRepository): Response
    {
        $host = $this->getHostUser();

        return $this->render('host_calendar/index.html.twig', [
            'properties' => $propertyRepository->findForHost($host),
        ]);
    }

    #[Route('/properties/{id}/calendar', name: 'app_host_property_calendar', methods: ['GET', 'POST'])]
    public function calendar(
        Request $request,
        Property $property,
        PropertyRepository $propertyRepository,
        PropertyAvailabilityRepository $availabilityRepository,
        PropertyICalSyncRepository $iCalSyncRepository,
        ReservationRepository $reservationRepository,
        PropertyAvailabilityManager $availabilityManager,
    ): Response {
        $host = $this->getHostUser();
        $property = $propertyRepository->findOneForHost($property, $host);

        if ($property === null) {
            throw $this->createNotFoundException();
        }

        $availabilityForm = $this->createForm(HostAvailabilityBlockType::class);
        $availabilityForm->handleRequest($request);

        if ($availabilityForm->isSubmitted() && $availabilityForm->isValid()) {
            $data = $availabilityForm->getData();

            try {
                $blocked = $availabilityManager->blockPeriod(
                    $property,
                    $this->asImmutableDate($data['startDate'] ?? null),
                    $this->asImmutableDate($data['endDate'] ?? null),
                    $data['priceOverride'] !== null ? (float) $data['priceOverride'] : null,
                    $data['minimumStay'] !== null ? (int) $data['minimumStay'] : null,
                );

                $this->addFlash('success', sprintf('%d nuit%s bloquée%s.', $blocked, $blocked > 1 ? 's' : '', $blocked > 1 ? 's' : ''));

                return $this->redirectToRoute('app_host_property_calendar', [
                    'id' => $property->getId(),
                ], Response::HTTP_SEE_OTHER);
            } catch (\DomainException $exception) {
                $availabilityForm->addError(new FormError($exception->getMessage()));
            }
        }

        $calendarMonth = $this->resolveCalendarMonth($request->query->getString('month'));
        $calendarStart = $calendarMonth->modify('monday this week');
        $calendarEnd = $calendarMonth->modify('last day of this month')->modify('sunday this week')->modify('+1 day');
        $calendarBlockedDates = $availabilityRepository->findBlockedForPropertyBetween($property, $calendarStart, $calendarEnd);
        $calendarReservations = $reservationRepository->findForPropertyCalendar($property, $calendarStart, $calendarEnd);

        return $this->render('host_calendar/show.html.twig', [
            'property' => $property,
            'availabilityForm' => $availabilityForm,
            'syncForm' => $this->createForm(HostICalSyncType::class),
            'blockedDates' => $availabilityRepository->findBlockedForProperty($property, new \DateTimeImmutable('today')),
            'iCalSyncs' => $iCalSyncRepository->findForProperty($property),
            'calendarMonth' => $calendarMonth,
            'previousMonth' => $calendarMonth->modify('-1 month'),
            'nextMonth' => $calendarMonth->modify('+1 month'),
            'calendarDays' => $this->buildCalendarDays($calendarMonth, $calendarStart, $calendarEnd, $calendarBlockedDates, $calendarReservations),
        ]);
    }

    #[Route('/properties/{id}/calendar/export-token', name: 'app_host_property_calendar_export_token', methods: ['POST'])]
    public function generateExportToken(
        Request $request,
        Property $property,
        PropertyRepository $propertyRepository,
        PropertyICalManager $iCalManager,
    ): Response {
        $host = $this->getHostUser();
        $property = $propertyRepository->findOneForHost($property, $host);

        if ($property === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('host_ical_token'.$property->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Jeton de securite invalide.');

            return $this->redirectToRoute('app_host_property_calendar', ['id' => $property->getId()], Response::HTTP_SEE_OTHER);
        }

        $iCalManager->generateExportToken($property);
        $this->addFlash('success', 'Le lien iCal prive a ete genere.');

        return $this->redirectToRoute('app_host_property_calendar', ['id' => $property->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/properties/{id}/calendar/export-token/revoke', name: 'app_host_property_calendar_revoke_export_token', methods: ['POST'])]
    public function revokeExportToken(
        Request $request,
        Property $property,
        PropertyRepository $propertyRepository,
        PropertyICalManager $iCalManager,
    ): Response {
        $host = $this->getHostUser();
        $property = $propertyRepository->findOneForHost($property, $host);

        if ($property === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('host_ical_revoke'.$property->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Jeton de securite invalide.');

            return $this->redirectToRoute('app_host_property_calendar', ['id' => $property->getId()], Response::HTTP_SEE_OTHER);
        }

        $iCalManager->revokeExportToken($property);
        $this->addFlash('success', 'Le lien iCal prive a ete revoque.');

        return $this->redirectToRoute('app_host_property_calendar', ['id' => $property->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/properties/{id}/calendar/imports', name: 'app_host_property_calendar_import_add', methods: ['POST'])]
    public function addImport(
        Request $request,
        Property $property,
        PropertyRepository $propertyRepository,
        PropertyICalManager $iCalManager,
    ): Response {
        $host = $this->getHostUser();
        $property = $propertyRepository->findOneForHost($property, $host);

        if ($property === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('host_ical_import'.$property->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Jeton de securite invalide.');

            return $this->redirectToRoute('app_host_property_calendar', ['id' => $property->getId()], Response::HTTP_SEE_OTHER);
        }

        $form = $this->createForm(HostICalSyncType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $iCalManager->addImport($property, (string) $data['providerName'], (string) $data['iCalUrl']);
            $this->addFlash('success', 'Le flux iCal a ete ajoute.');
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }

            if (!$form->isSubmitted() || count($form->getErrors(true)) === 0) {
                $this->addFlash('error', 'Le flux iCal est invalide.');
            }
        }

        return $this->redirectToRoute('app_host_property_calendar', ['id' => $property->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/properties/{id}/calendar/imports/{syncId}/delete', name: 'app_host_property_calendar_import_delete', methods: ['POST'])]
    public function deleteImport(
        Request $request,
        Property $property,
        string $syncId,
        PropertyRepository $propertyRepository,
        PropertyICalSyncRepository $iCalSyncRepository,
        PropertyICalManager $iCalManager,
    ): Response {
        $host = $this->getHostUser();
        $property = $propertyRepository->findOneForHost($property, $host);

        if ($property === null) {
            throw $this->createNotFoundException();
        }

        $sync = $iCalSyncRepository->findOneForProperty($property, $syncId);
        if ($sync === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('host_ical_delete'.$sync->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Jeton de securite invalide.');

            return $this->redirectToRoute('app_host_property_calendar', ['id' => $property->getId()], Response::HTTP_SEE_OTHER);
        }

        $iCalManager->removeImport($sync);
        $this->addFlash('success', 'Le flux iCal a ete supprime.');

        return $this->redirectToRoute('app_host_property_calendar', ['id' => $property->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/properties/{id}/calendar/imports/{syncId}/sync', name: 'app_host_property_calendar_import_sync', methods: ['POST'])]
    public function syncImport(
        Request $request,
        Property $property,
        string $syncId,
        PropertyRepository $propertyRepository,
        PropertyICalSyncRepository $iCalSyncRepository,
        ICalSyncService $iCalSyncService,
    ): Response {
        $host = $this->getHostUser();
        $property = $propertyRepository->findOneForHost($property, $host);

        if ($property === null) {
            throw $this->createNotFoundException();
        }

        $sync = $iCalSyncRepository->findOneForProperty($property, $syncId);
        if ($sync === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('host_ical_sync_now'.$sync->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Jeton de securite invalide.');

            return $this->redirectToRoute('app_host_property_calendar', ['id' => $property->getId()], Response::HTTP_SEE_OTHER);
        }

        try {
            $blocked = $iCalSyncService->sync($sync);
            $this->addFlash('success', sprintf('%d nuit%s synchronisee%s depuis %s.', $blocked, $blocked > 1 ? 's' : '', $blocked > 1 ? 's' : '', $sync->getProviderName()));
        } catch (\Throwable $exception) {
            $this->addFlash('error', sprintf('Synchronisation impossible pour %s : %s', $sync->getProviderName(), $exception->getMessage()));
        }

        return $this->redirectToRoute('app_host_property_calendar', ['id' => $property->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/properties/{id}/calendar/{availabilityId}/unblock', name: 'app_host_property_calendar_unblock', methods: ['POST'])]
    public function unblock(
        Request $request,
        Property $property,
        string $availabilityId,
        PropertyRepository $propertyRepository,
        PropertyAvailabilityRepository $availabilityRepository,
        PropertyAvailabilityManager $availabilityManager,
    ): Response {
        $host = $this->getHostUser();
        $property = $propertyRepository->findOneForHost($property, $host);

        if ($property === null) {
            throw $this->createNotFoundException();
        }

        $availability = $availabilityRepository->findOneForProperty($property, $availabilityId);
        if ($availability === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('host_unblock'.$availability->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_host_property_calendar', [
                'id' => $property->getId(),
            ], Response::HTTP_SEE_OTHER);
        }

        $availabilityManager->unblock($availability);
        $this->addFlash('success', 'La date est de nouveau disponible.');

        return $this->redirectToRoute('app_host_property_calendar', [
            'id' => $property->getId(),
        ], Response::HTTP_SEE_OTHER);
    }

    private function getHostUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function asImmutableDate(mixed $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        throw new \DomainException('Les dates sont obligatoires.');
    }

    private function resolveCalendarMonth(string $month): \DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $month.'-01');
            if ($date instanceof \DateTimeImmutable) {
                return $date;
            }
        }

        return new \DateTimeImmutable('first day of this month');
    }

    /**
     * @param list<\App\Entity\PropertyAvailability> $blockedDates
     * @param list<\App\Entity\Reservation> $reservations
     *
     * @return list<array{date: \DateTimeImmutable, key: string, dayNumber: string, isCurrentMonth: bool, isPast: bool, isToday: bool, isBlocked: bool, reservations: list<array{status: string, label: string}>}>
     */
    private function buildCalendarDays(
        \DateTimeImmutable $calendarMonth,
        \DateTimeImmutable $calendarStart,
        \DateTimeImmutable $calendarEnd,
        array $blockedDates,
        array $reservations,
    ): array {
        $blockedByDate = [];
        foreach ($blockedDates as $blockedDate) {
            $date = $blockedDate->getAvailableDate();
            if ($date !== null) {
                $blockedByDate[$date->format('Y-m-d')] = true;
            }
        }

        $days = [];
        $todayKey = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $cursor = $calendarStart;

        while ($cursor < $calendarEnd) {
            $key = $cursor->format('Y-m-d');
            $dayReservations = [];

            foreach ($reservations as $reservation) {
                $checkinDate = $reservation->getCheckinDate();
                $checkoutDate = $reservation->getCheckoutDate();
                if ($checkinDate === null || $checkoutDate === null || $cursor < $checkinDate || $cursor >= $checkoutDate) {
                    continue;
                }

                $dayReservations[] = [
                    'status' => $reservation->getStatus() ?? 'pending',
                    'label' => $reservation->getStatus() === 'confirmed' ? 'Reservation confirmee' : 'Demande en attente',
                ];
            }

            $days[] = [
                'date' => $cursor,
                'key' => $key,
                'dayNumber' => $cursor->format('j'),
                'isCurrentMonth' => $cursor->format('Y-m') === $calendarMonth->format('Y-m'),
                'isPast' => $key < $todayKey,
                'isToday' => $key === $todayKey,
                'isBlocked' => isset($blockedByDate[$key]),
                'reservations' => $dayReservations,
            ];

            $cursor = $cursor->modify('+1 day');
        }

        return $days;
    }
}
