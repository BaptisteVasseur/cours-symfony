<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PropertyAvailability;
use App\Entity\PropertyICalSync;
use App\Repository\PropertyAvailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ICalImportService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly PropertyAvailabilityRepository $availabilityRepository,
    ) {}

    /**
     * Fetches an external iCal feed, parses VEVENT blocks, and creates PropertyAvailability
     * rows (isAvailable=false) for each blocked date range found.
     * Updates PropertyICalSync.lastSyncAt.
     * Returns the count of new blocked days created.
     */
    public function sync(PropertyICalSync $sync): int
    {
        $response = $this->httpClient->request('GET', $sync->getICalUrl());
        $icsContent = $response->getContent();

        $events = $this->parseVevents($icsContent);
        $property = $sync->getProperty();
        $count = 0;

        foreach ($events as $event) {
            $start = $event['start'];
            $end = $event['end'];

            if ($start === null || $end === null || $start >= $end) {
                continue;
            }

            // Remove existing blocked rows in range to avoid duplicates
            $this->availabilityRepository->deleteBlockedInRange($property, $start, $end);

            $cursor = $start;
            while ($cursor < $end) {
                $row = new PropertyAvailability();
                $row->setProperty($property);
                $row->setAvailableDate($cursor);
                $row->setIsAvailable(false);
                $this->em->persist($row);
                $cursor = $cursor->modify('+1 day');
                $count++;
            }
        }

        $sync->setLastSyncAt(new \DateTimeImmutable());
        $this->em->flush();

        return $count;
    }

    /**
     * Minimal RFC 5545 parser: extracts DTSTART/DTEND pairs from raw .ics string.
     *
     * @return list<array{start: \DateTimeImmutable|null, end: \DateTimeImmutable|null}>
     */
    private function parseVevents(string $icsContent): array
    {
        $events = [];
        $lines = preg_split('/\r\n|\r|\n/', $icsContent);
        $inEvent = false;
        $current = ['start' => null, 'end' => null];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === 'BEGIN:VEVENT') {
                $inEvent = true;
                $current = ['start' => null, 'end' => null];
                continue;
            }

            if ($line === 'END:VEVENT') {
                $inEvent = false;
                $events[] = $current;
                continue;
            }

            if (!$inEvent) {
                continue;
            }

            if (str_starts_with($line, 'DTSTART')) {
                $current['start'] = $this->parseIcalDate($line);
            } elseif (str_starts_with($line, 'DTEND')) {
                $current['end'] = $this->parseIcalDate($line);
            }
        }

        return $events;
    }

    private function parseIcalDate(string $line): ?\DateTimeImmutable
    {
        // Extract value after the colon (handles VALUE=DATE: and TZID= variants)
        $colonPos = strpos($line, ':');
        if ($colonPos === false) {
            return null;
        }
        $value = substr($line, $colonPos + 1);
        $value = trim($value);

        // DATE format: 20260710
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $m)) {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%s-%s-%s', $m[1], $m[2], $m[3]));
            return $date !== false ? $date->setTime(0, 0, 0) : null;
        }

        // DATETIME format: 20260710T150000Z or 20260710T150000
        if (preg_match('/^(\d{8})T\d{6}Z?$/', $value, $m)) {
            $date = \DateTimeImmutable::createFromFormat('Ymd', $m[1]);
            return $date !== false ? $date->setTime(0, 0, 0) : null;
        }

        return null;
    }
}
