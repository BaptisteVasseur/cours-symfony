<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PropertyAvailability;
use App\Entity\PropertyICalSync;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Imports an external iCal feed and blocks the corresponding nights on the local calendar.
 *
 * Conflict strategy:
 *  - Imported rows are tagged with the source ('import:<provider>') so a re-sync only reconciles
 *    its own rows and never disturbs host-manual blocks.
 *  - A remote event overlapping an existing CONFIRMED reservation is skipped (reported, not blocked):
 *    the local booking already owns those dates.
 *  - Rows previously imported but no longer present remotely are removed (the remote freed them).
 */
final class ICalImporter
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly PropertyAvailabilityRepository $availability,
        private readonly ReservationRepository $reservations,
    ) {
    }

    public function import(PropertyICalSync $sync): ICalImportResult
    {
        $property = $sync->getProperty();
        $sourceTag = 'import:' . ($sync->getProviderName() ?? 'ical');

        $content = $this->httpClient->request('GET', (string) $sync->getICalUrl())->getContent();
        $events = $this->parseEvents($content);

        $desired = [];
        $skipped = 0;
        foreach ($events as [$start, $end]) {
            if ($this->reservations->findConflicting($property, $start, $end) !== []) {
                ++$skipped;
                continue;
            }
            for ($day = $start; $day < $end; $day = $day->modify('+1 day')) {
                $desired[$day->format('Y-m-d')] = $day;
            }
        }

        $existing = [];
        foreach ($this->availability->findBySource($property, $sourceTag) as $row) {
            $existing[$row->getAvailableDate()->format('Y-m-d')] = $row;
        }

        $created = 0;
        foreach ($desired as $key => $date) {
            if (isset($existing[$key])) {
                unset($existing[$key]);
                continue;
            }

            $row = new PropertyAvailability();
            $row->setProperty($property);
            $row->setAvailableDate($date);
            $row->setIsAvailable(false);
            $row->setSource($sourceTag);
            $this->em->persist($row);
            ++$created;
        }

        $removed = 0;
        foreach ($existing as $row) {
            $this->em->remove($row);
            ++$removed;
        }

        $sync->setLastSyncAt(new \DateTimeImmutable());
        $this->em->flush();

        return new ICalImportResult(\count($events), $created, $removed, $skipped);
    }

    /**
     * @return list<array{0: \DateTimeImmutable, 1: \DateTimeImmutable}> list of [start, end) ranges
     */
    private function parseEvents(string $ics): array
    {
        $ics = preg_replace("/\r?\n[ \t]/", '', $ics) ?? $ics;

        $events = [];
        foreach (preg_split('/BEGIN:VEVENT/', $ics) ?: [] as $block) {
            $start = $this->extractDate($block, 'DTSTART');
            if ($start === null) {
                continue;
            }
            $end = $this->extractDate($block, 'DTEND') ?? $start->modify('+1 day');
            $events[] = [$start, $end];
        }

        return $events;
    }

    private function extractDate(string $block, string $field): ?\DateTimeImmutable
    {
        if (preg_match('/' . $field . '[^:\r\n]*:(\d{8})/', $block, $matches) !== 1) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Ymd', $matches[1]);

        return $date === false ? null : $date;
    }
}
