<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PropertyAvailability;
use App\Entity\PropertyICalSync;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ICalImportService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PropertyAvailabilityRepository $availabilityRepository,
        private readonly ReservationRepository $reservationRepository,
    ) {}

    /**
     * Fetches the external iCal URL and blocks corresponding nights in PropertyAvailability.
     *
     * Returns an array with counts: ['blocked' => int, 'skipped' => int, 'conflicts' => int]
     *
     * @return array{blocked: int, skipped: int, conflicts: int}
     */
    public function syncForProperty(PropertyICalSync $sync): array
    {
        $content = $this->fetchUrl($sync->getICalUrl());
        if ($content === null) {
            throw new \RuntimeException(sprintf('Could not fetch iCal URL: %s', $sync->getICalUrl()));
        }

        $events = $this->parseEvents($content);
        $property = $sync->getProperty();
        $stats = ['blocked' => 0, 'skipped' => 0, 'conflicts' => 0];

        foreach ($events as $event) {
            $start = $event['start'];
            $end = $event['end'];

            // Check for conflicts with existing confirmed reservations
            $conflicts = $this->reservationRepository->findConfirmedOverlapping($property, $start, $end);
            if (\count($conflicts) > 0) {
                ++$stats['conflicts'];
                continue;
            }

            // Block each day in the range
            $existing = $this->availabilityRepository->findBlockedInRange($property, $start, $end);
            $blockedMap = [];
            foreach ($existing as $pa) {
                $blockedMap[$pa->getAvailableDate()->format('Y-m-d')] = true;
            }

            $current = $start;
            while ($current < $end) {
                $key = $current->format('Y-m-d');
                if (!isset($blockedMap[$key])) {
                    $pa = new PropertyAvailability();
                    $pa->setProperty($property);
                    $pa->setAvailableDate($current);
                    $pa->setIsAvailable(false);
                    $this->em->persist($pa);
                    ++$stats['blocked'];
                } else {
                    ++$stats['skipped'];
                }
                $current = $current->modify('+1 day');
            }
        }

        $sync->setLastSyncAt(new \DateTimeImmutable());
        $this->em->flush();

        return $stats;
    }

    /**
     * Fetches a URL and returns its content, or null on failure.
     */
    private function fetchUrl(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'user_agent' => 'CloneAirbnb/iCalSync',
            ],
        ]);

        $content = @file_get_contents($url, false, $context);

        return $content !== false ? $content : null;
    }

    /**
     * Parses VEVENT blocks from a raw iCal string.
     *
     * @return array<array{start: \DateTimeImmutable, end: \DateTimeImmutable, uid: string}>
     */
    private function parseEvents(string $content): array
    {
        $events = [];
        // Unfold long lines (RFC 5545 line folding: CRLF + whitespace)
        $content = preg_replace("/\r\n[ \t]/", '', $content) ?? $content;
        $content = preg_replace("/\n[ \t]/", '', $content) ?? $content;

        preg_match_all('/BEGIN:VEVENT(.+?)END:VEVENT/s', $content, $matches);

        foreach ($matches[1] as $block) {
            $uid = $this->extractLine($block, 'UID') ?? uniqid('import-', true);
            $startRaw = $this->extractLine($block, 'DTSTART');
            $endRaw = $this->extractLine($block, 'DTEND');

            if ($startRaw === null || $endRaw === null) {
                continue;
            }

            $start = $this->parseICalDate($startRaw);
            $end = $this->parseICalDate($endRaw);

            if ($start === null || $end === null || $end <= $start) {
                continue;
            }

            $events[] = ['start' => $start, 'end' => $end, 'uid' => $uid];
        }

        return $events;
    }

    private function extractLine(string $block, string $property): ?string
    {
        // Matches PROPERTY:value or PROPERTY;PARAM=...:value
        if (preg_match('/^' . preg_quote($property, '/') . '[;:][^\r\n]*[:;]?([^\r\n]+)/m', $block, $m)) {
            // Get the value after the last colon on the matched line
            $full = trim($m[0]);
            $colonPos = strrpos($full, ':');
            if ($colonPos !== false) {
                return trim(substr($full, $colonPos + 1));
            }
        }

        return null;
    }

    private function parseICalDate(string $raw): ?\DateTimeImmutable
    {
        // DATE format: YYYYMMDD
        if (preg_match('/^(\d{8})$/', $raw, $m)) {
            $dt = \DateTimeImmutable::createFromFormat('Ymd', $m[1]);
            return $dt !== false ? $dt->setTime(0, 0, 0) : null;
        }

        // DATETIME format: YYYYMMDDTHHmmssZ
        if (preg_match('/^(\d{8}T\d{6}Z?)$/', $raw, $m)) {
            $format = strlen($m[1]) === 16 ? 'Ymd\THis\Z' : 'Ymd\THis';
            $dt = \DateTimeImmutable::createFromFormat($format, $m[1], new \DateTimeZone('UTC'));
            return $dt !== false ? $dt->setTime(0, 0, 0) : null;
        }

        return null;
    }
}
