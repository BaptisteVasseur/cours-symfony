<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PropertyAvailability;
use App\Entity\PropertyICalSync;
use App\Repository\PropertyAvailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ICalImportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PropertyAvailabilityRepository $propertyAvailabilityRepository,
    ) {
    }

    /**
     * @return array{ranges:int,days:int,created:int,updated:int,skipped:int}
     */
    public function sync(PropertyICalSync $sync): array
    {
        $property = $sync->getProperty();
        if ($property === null) {
            throw new \RuntimeException('Aucune propriete associee a cette source iCal.');
        }

        $content = $this->downloadCalendar((string) $sync->getICalUrl());
        $ranges = $this->parseDateRanges($content);
        $blockedDates = $this->expandRangesToDates($ranges);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($blockedDates as $date) {
            $availability = $this->propertyAvailabilityRepository->findOneForPropertyAndDate($property, $date);

            if (!$availability instanceof PropertyAvailability) {
                $availability = new PropertyAvailability();
                $availability->setProperty($property);
                $availability->setAvailableDate($date);
                $availability->setIsAvailable(false);
                $this->entityManager->persist($availability);
                $created++;
                continue;
            }

            if ($availability->isAvailable()) {
                $availability->setIsAvailable(false);
                $updated++;
                continue;
            }

            $skipped++;
        }

        $sync->setLastSyncAt(new \DateTimeImmutable());
        $this->entityManager->persist($sync);
        $this->entityManager->flush();

        return [
            'ranges' => count($ranges),
            'days' => count($blockedDates),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    private function downloadCalendar(string $url): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('URL iCal invalide.');
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'header' => "User-Agent: AirbnbCloneICalSync/1.0\r\n",
            ],
        ]);

        $content = @file_get_contents($url, false, $context);
        if ($content === false || trim($content) === '') {
            throw new \RuntimeException('Impossible de recuperer le flux iCal.');
        }

        return $content;
    }

    /**
     * @return list<array{start:\DateTimeImmutable,end:\DateTimeImmutable}>
     */
    private function parseDateRanges(string $content): array
    {
        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $content, $matches);

        $ranges = [];

        foreach ($matches[1] ?? [] as $eventBlock) {
            $startRaw = $this->extractDateValue($eventBlock, 'DTSTART');
            if ($startRaw === null) {
                continue;
            }

            $start = $this->createDateFromRaw($startRaw);
            if (!$start instanceof \DateTimeImmutable) {
                continue;
            }

            $endRaw = $this->extractDateValue($eventBlock, 'DTEND');
            $end = $endRaw ? $this->createDateFromRaw($endRaw) : null;

            if (!$end instanceof \DateTimeImmutable || $end <= $start) {
                $end = $start->modify('+1 day');
            }

            $ranges[] = [
                'start' => $start,
                'end' => $end,
            ];
        }

        return $ranges;
    }

    private function extractDateValue(string $eventBlock, string $field): ?string
    {
        $normalized = preg_replace("/\r\n[ \t]/", '', $eventBlock);
        if (!is_string($normalized)) {
            return null;
        }

        if (!preg_match('/^' . preg_quote($field, '/') . '(?:;[^:]+)?:([^\r\n]+)/m', $normalized, $match)) {
            return null;
        }

        return trim($match[1]);
    }

    private function createDateFromRaw(string $raw): ?\DateTimeImmutable
    {
        if (!preg_match('/^(\d{8})/', $raw, $match)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Ymd', $match[1]);

        return $date ?: null;
    }

    /**
     * @param list<array{start:\DateTimeImmutable,end:\DateTimeImmutable}> $ranges
     *
     * @return list<\DateTimeImmutable>
     */
    private function expandRangesToDates(array $ranges): array
    {
        $minDate = (new \DateTimeImmutable('today'))->modify('-30 days');
        $maxDate = (new \DateTimeImmutable('today'))->modify('+730 days');

        $byKey = [];

        foreach ($ranges as $range) {
            $current = $range['start'];
            $lastIncluded = $range['end']->modify('-1 day');

            while ($current <= $lastIncluded) {
                if ($current >= $minDate && $current <= $maxDate) {
                    $byKey[$current->format('Y-m-d')] = $current;
                }

                $current = $current->modify('+1 day');
            }
        }

        ksort($byKey);

        return array_values($byKey);
    }
}

