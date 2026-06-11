<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\PropertyICalSync;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ICalImporter
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly PropertyAvailabilityRepository $availabilities,
        private readonly ReservationRepository $reservations,
    ) {
    }

    /**
     * @return array{imported: int, removed: int, conflicts: list<string>}
     */
    public function import(PropertyICalSync $sync): array
    {
        $property = $sync->getProperty();
        if ($property === null) {
            throw new \RuntimeException('Flux iCal sans logement associé.');
        }

        $content = $this->fetch($sync->getICalUrl());
        $events = $this->parse($content);
        $confirmedReservations = $this->reservations->findConfirmedForProperty($property);

        $existing = [];
        foreach ($this->availabilities->findImportedForProperty($property) as $row) {
            $existing[$row->getSourceUid()] = $row;
        }

        $seenUids = [];
        $imported = 0;
        $conflicts = [];

        foreach ($events as $event) {
            $uid = $event['uid'];
            $seenUids[$uid] = true;

            foreach ($confirmedReservations as $reservation) {
                if ($reservation->getCheckinDate() < $event['end'] && $reservation->getCheckoutDate() > $event['start']) {
                    $conflicts[] = sprintf(
                        'Conflit: événement distant %s (%s → %s) chevauche la réservation confirmée %s.',
                        $uid,
                        $event['start']->format('Y-m-d'),
                        $event['end']->format('Y-m-d'),
                        (string) $reservation->getId(),
                    );
                    break;
                }
            }

            $this->applyEvent($property, $event);
            ++$imported;
        }

        $removed = 0;
        foreach ($existing as $uid => $row) {
            if (!isset($seenUids[$uid])) {
                $this->removeImportedDays($property, $row->getSourceUid());
                ++$removed;
            }
        }

        $sync->setLastSyncAt(new \DateTimeImmutable());
        $this->em->flush();

        return ['imported' => $imported, 'removed' => $removed, 'conflicts' => $conflicts];
    }

    private function fetch(?string $url): string
    {
        if ($url === null || trim($url) === '') {
            throw new \RuntimeException('URL iCal vide.');
        }

        $response = $this->httpClient->request('GET', $url, ['timeout' => 15]);
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Flux injoignable (HTTP %d).', $response->getStatusCode()));
        }

        return $response->getContent();
    }

    /**
     * @return list<array{uid: string, start: \DateTimeImmutable, end: \DateTimeImmutable}>
     */
    private function parse(string $content): array
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $events = [];
        $current = null;

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if ($line === 'BEGIN:VEVENT') {
                $current = ['uid' => null, 'start' => null, 'end' => null];
                continue;
            }

            if ($line === 'END:VEVENT') {
                if ($current !== null && $current['start'] !== null && $current['end'] !== null) {
                    $events[] = [
                        'uid' => $current['uid'] ?? ('gen-'.$current['start']->format('Ymd').'-'.$current['end']->format('Ymd')),
                        'start' => $current['start'],
                        'end' => $current['end'],
                    ];
                }
                $current = null;
                continue;
            }

            if ($current === null) {
                continue;
            }

            if (str_starts_with($line, 'UID')) {
                $current['uid'] = $this->value($line);
            } elseif (str_starts_with($line, 'DTSTART')) {
                $current['start'] = $this->parseDate($this->value($line));
            } elseif (str_starts_with($line, 'DTEND')) {
                $current['end'] = $this->parseDate($this->value($line));
            }
        }

        return array_values(array_filter(
            $events,
            static fn (array $e): bool => $e['start'] !== null && $e['end'] !== null && $e['end'] > $e['start'],
        ));
    }

    private function value(string $line): string
    {
        $pos = strpos($line, ':');

        return $pos === false ? '' : trim(substr($line, $pos + 1));
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $value = substr($value, 0, 8);
        $date = \DateTimeImmutable::createFromFormat('!Ymd', $value);

        return $date !== false ? $date : null;
    }

    /**
     * @param array{uid: string, start: \DateTimeImmutable, end: \DateTimeImmutable} $event
     */
    private function applyEvent(Property $property, array $event): void
    {
        $this->removeImportedDays($property, $event['uid']);

        $cursor = $event['start'];
        while ($cursor < $event['end']) {
            $availability = new PropertyAvailability();
            $availability->setProperty($property);
            $availability->setAvailableDate($cursor);
            $availability->setIsAvailable(false);
            $availability->setSource('ical_import');
            $availability->setSourceUid($event['uid']);
            $this->em->persist($availability);

            $cursor = $cursor->modify('+1 day');
        }
    }

    private function removeImportedDays(Property $property, ?string $uid): void
    {
        if ($uid === null) {
            return;
        }

        foreach ($this->availabilities->findBySourceUid($property, $uid) as $row) {
            $this->em->remove($row);
        }
    }
}
