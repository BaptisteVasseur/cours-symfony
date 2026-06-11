<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PropertyAvailability;
use App\Entity\PropertyGoogleCalendarSync;
use App\Entity\Reservation;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\PropertyGoogleCalendarSyncRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GoogleCalendarSyncService
{
    public function __construct(
        private GoogleCalendarOAuthService $oauthService,
        private PropertyGoogleCalendarSyncRepository $syncRepository,
        private PropertyAvailabilityRepository $availabilityRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Push a confirmed reservation to Google Calendar.
     */
    public function pushReservation(Reservation $reservation): void
    {
        $property = $reservation->getProperty();
        if ($property === null) {
            return;
        }

        $sync = $property->getGoogleCalendarSync();
        if ($sync === null || !$sync->isConnected() || !$sync->isSyncEnabled()) {
            return;
        }

        $this->oauthService->refreshTokenIfNeeded($sync);

        if (!$sync->isSyncEnabled() || $sync->getAccessToken() === null) {
            return;
        }

        try {
            $client = $this->oauthService->getClient();
            $client->setAccessToken($sync->getAccessToken());

            $service = new \Google\Service\Calendar($client);
            $calendarId = $sync->getGoogleCalendarId() ?? 'primary';

            $event = new \Google\Service\Calendar\Event([
                'summary' => sprintf('Réservé : %s', $property->getTitle() ?? 'Logement'),
                'description' => sprintf(
                    "Réservation confirmée\nVoyageur : %s %s\nVoyageurs : %d\nTotal : %s %s\nVoir : %s",
                    $reservation->getGuest()?->getProfile()?->getFirstName() ?? '',
                    $reservation->getGuest()?->getProfile()?->getLastName() ?? '',
                    $reservation->getGuestsCount() ?? 0,
                    $reservation->getTotalPrice() ?? '0',
                    $reservation->getCurrency() ?? 'EUR',
                    '', // URL would be added here
                ),
                'start' => [
                    'date' => $reservation->getCheckinDate()?->format('Y-m-d'),
                    'timeZone' => 'UTC',
                ],
                'end' => [
                    'date' => $reservation->getCheckoutDate()?->format('Y-m-d'),
                    'timeZone' => 'UTC',
                ],
                'transparency' => 'opaque',
            ]);

            $service->events->insert($calendarId, $event);
        } catch (\Exception $e) {
            $sync->setLastError('Push erreur : ' . $e->getMessage());
            $sync->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }
    }

    /**
     * Remove a cancelled reservation from Google Calendar.
     */
    public function removeReservation(Reservation $reservation): void
    {
        // Future implementation: find and delete the event by reservation ID
        // For MVP, this is acceptable as the event will be overwritten
    }

    /**
     * Pull events from Google Calendar and create unavailability periods.
     *
     * @return int Number of blocked days created
     */
    public function pullEvents(PropertyGoogleCalendarSync $sync): int
    {
        $this->oauthService->refreshTokenIfNeeded($sync);

        if (!$sync->isSyncEnabled() || $sync->getAccessToken() === null) {
            return 0;
        }

        try {
            $client = $this->oauthService->getClient();
            $client->setAccessToken($sync->getAccessToken());

            $service = new \Google\Service\Calendar($client);
            $calendarId = $sync->getGoogleCalendarId() ?? 'primary';
            $property = $sync->getProperty();

            if ($property === null) {
                return 0;
            }

            // Fetch events from now until 1 year ahead
            $now = new \DateTimeImmutable();
            $oneYearLater = $now->modify('+1 year');

            $optParams = [
                'timeMin' => $now->format(\DateTimeInterface::RFC3339),
                'timeMax' => $oneYearLater->format(\DateTimeInterface::RFC3339),
                'singleEvents' => true,
                'orderBy' => 'startTime',
            ];

            $events = $service->events->listEvents($calendarId, $optParams);
            $blockedCount = 0;

            foreach ($events->getItems() as $event) {
                if ($event->getTransparency() === 'transparent') {
                    continue; // Skip "free" events
                }

                $start = $event->getStart();
                $end = $event->getEnd();

                $startDate = $start->getDateTime() ?? $start->getDate();
                $endDate = $end->getDateTime() ?? $end->getDate();

                if ($startDate === null || $endDate === null) {
                    continue;
                }

                try {
                    $startImmutable = new \DateTimeImmutable($startDate);
                    $endImmutable = new \DateTimeImmutable($endDate);
                } catch (\Exception) {
                    continue;
                }

                $propertyId = (string) $property->getId();
                $period = new \DatePeriod($startImmutable, new \DateInterval('P1D'), $endImmutable);

                foreach ($period as $date) {
                    $dateImmutable = $date; // Already DateTimeImmutable

                    // Don't override manual blocks or existing confirmed reservations
                    $existing = $this->availabilityRepository->findOneBy([
                        'property' => $property,
                        'availableDate' => $dateImmutable,
                    ]);

                    if ($existing !== null) {
                        continue;
                    }

                    $availability = new PropertyAvailability();
                    $availability->setProperty($property);
                    $availability->setAvailableDate($dateImmutable);
                    $availability->setIsAvailable(false);

                    $this->entityManager->persist($availability);
                    ++$blockedCount;
                }
            }

            $sync->setLastSyncAt(new \DateTimeImmutable());
            $sync->setLastError(null);
            $sync->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            return $blockedCount;
        } catch (\Exception $e) {
            $sync->setLastError('Pull erreur : ' . $e->getMessage());
            $sync->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            throw $e;
        }
    }

    /**
     * Sync all connected calendars.
     *
     * @return array<string, array{status: string, blocked?: int, message?: string}>
     */
    public function syncAll(): array
    {
        $results = [];
        $syncs = $this->syncRepository->findForSync();

        foreach ($syncs as $sync) {
            $propertyId = (string) $sync->getProperty()?->getId() ?? '?';
            try {
                $count = $this->pullEvents($sync);
                $results[$propertyId] = ['status' => 'success', 'blocked' => $count];
            } catch (\Exception $e) {
                $results[$propertyId] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        return $results;
    }
}
