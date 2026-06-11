<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PropertyAvailability;
use App\Entity\PropertyICalSync;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class IcalImportService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private PropertyAvailabilityRepository $availabilityRepository,
        private ReservationRepository $reservationRepository,
    ) {
    }

    /**
     * @return array{created: int, updated: int, removed: int, conflicts: int, skipped: int}
     */
    public function sync(PropertyICalSync $sync): array
    {
        $property = $sync->getProperty();
        if ($property === null) {
            throw new \DomainException('Le flux iCal n\'est rattache a aucun logement.');
        }

        $runStartedAt = new \DateTimeImmutable();
        $events = $this->fetchEvents($sync);
        $result = [
            'created' => 0,
            'updated' => 0,
            'removed' => 0,
            'conflicts' => 0,
            'skipped' => 0,
        ];

        foreach ($events as $event) {
            if ($event['dateStart'] >= $event['dateEnd']) {
                ++$result['skipped'];
                continue;
            }

            $existing = $this->availabilityRepository->findImportedEvent($sync, $event['uid']);
            if ($this->reservationRepository->hasConfirmedOverlap($property, $event['dateStart'], $event['dateEnd'])) {
                if ($existing !== null) {
                    $this->entityManager->remove($existing);
                }
                ++$result['conflicts'];
                continue;
            }

            $availability = $existing ?? new PropertyAvailability();
            $availability->setProperty($property);
            $availability->setDateStart($event['dateStart']);
            $availability->setDateEnd($event['dateEnd']);
            $availability->setAvailableDate($event['dateStart']);
            $availability->setIsAvailable(false);
            $availability->setSource(PropertyAvailability::SOURCE_ICAL);
            $availability->setExternalUid($event['uid']);
            $availability->setICalSync($sync);
            $availability->setLastSeenAt($runStartedAt);
            $availability->setReason($event['summary'] !== '' ? $event['summary'] : 'Import iCal');

            if ($existing === null) {
                $this->entityManager->persist($availability);
                ++$result['created'];
            } else {
                ++$result['updated'];
            }
        }

        foreach ($this->availabilityRepository->findImportedNotSeenSince($sync, $runStartedAt) as $staleAvailability) {
            $this->entityManager->remove($staleAvailability);
            ++$result['removed'];
        }

        $sync->setLastSyncAt($runStartedAt);
        $this->entityManager->flush();

        return $result;
    }

    /**
     * @return list<array{uid: string, summary: string, dateStart: \DateTimeImmutable, dateEnd: \DateTimeImmutable}>
     */
    private function fetchEvents(PropertyICalSync $sync): array
    {
        $response = $this->httpClient->request('GET', (string) $sync->getICalUrl(), [
            'timeout' => 15,
        ]);

        return $this->parseCalendar($response->getContent());
    }

    /**
     * @return list<array{uid: string, summary: string, dateStart: \DateTimeImmutable, dateEnd: \DateTimeImmutable}>
     */
    private function parseCalendar(string $content): array
    {
        $events = [];
        foreach ($this->extractEventBlocks($content) as $block) {
            $fields = $this->parseFields($block);
            $dateStart = $this->parseDate($fields['DTSTART'] ?? null);
            $dateEnd = $this->parseDate($fields['DTEND'] ?? null);

            if ($dateStart === null) {
                continue;
            }

            $dateEnd ??= $dateStart->modify('+1 day');
            $uid = trim($fields['UID'] ?? '');
            $summary = trim($fields['SUMMARY'] ?? '');

            if ($uid === '') {
                $uid = sha1($dateStart->format('Y-m-d') . '|' . $dateEnd->format('Y-m-d') . '|' . $summary);
            }

            $events[] = [
                'uid' => $uid,
                'summary' => $summary,
                'dateStart' => $dateStart,
                'dateEnd' => $dateEnd,
            ];
        }

        return $events;
    }

    /**
     * @return list<list<string>>
     */
    private function extractEventBlocks(string $content): array
    {
        $lines = $this->unfoldLines($content);
        $events = [];
        $currentEvent = null;

        foreach ($lines as $line) {
            if ($line === 'BEGIN:VEVENT') {
                $currentEvent = [];
                continue;
            }

            if ($line === 'END:VEVENT') {
                if ($currentEvent !== null) {
                    $events[] = $currentEvent;
                }
                $currentEvent = null;
                continue;
            }

            if ($currentEvent !== null) {
                $currentEvent[] = $line;
            }
        }

        return $events;
    }

    /**
     * @return list<string>
     */
    private function unfoldLines(string $content): array
    {
        $rawLines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        $lines = [];

        foreach ($rawLines as $line) {
            if ($line === '') {
                continue;
            }

            if (($line[0] === ' ' || $line[0] === "\t") && $lines !== []) {
                $lines[array_key_last($lines)] .= substr($line, 1);
                continue;
            }

            $lines[] = trim($line);
        }

        return $lines;
    }

    /**
     * @param list<string> $block
     * @return array<string, string>
     */
    private function parseFields(array $block): array
    {
        $fields = [];

        foreach ($block as $line) {
            $separatorPosition = strpos($line, ':');
            if ($separatorPosition === false) {
                continue;
            }

            $name = strtoupper(strtok(substr($line, 0, $separatorPosition), ';') ?: '');
            $value = substr($line, $separatorPosition + 1);
            $fields[$name] = $this->unescapeText($value);
        }

        return $fields;
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim($value);
        $formats = ['!Ymd', '!Ymd\THis\Z', '!Ymd\THis'];

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value, new \DateTimeZone('UTC'));
            if ($date instanceof \DateTimeImmutable) {
                return $date->setTime(0, 0);
            }
        }

        return null;
    }

    private function unescapeText(string $value): string
    {
        return str_replace(['\\n', '\\,', '\\;', '\\\\'], ["\n", ',', ';', '\\'], $value);
    }
}
