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

final class IcalImportService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly PropertyAvailabilityRepository $availabilityRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{imported: int, skipped: int, removed: int}
     */
    public function sync(PropertyICalSync $sync): array
    {
        $property = $sync->getProperty();
        if ($property === null) {
            return ['imported' => 0, 'skipped' => 0, 'removed' => 0];
        }

        $response = $this->httpClient->request('GET', (string) $sync->getICalUrl());
        $content = $response->getContent();
        $events = $this->parseEvents($content);

        $imported = 0;
        $skipped = 0;
        $activeUids = [];

        foreach ($events as $event) {
            $uid = $event['uid'];
            $activeUids[] = $uid;

            if ($this->reservationRepository->existsConfirmedOverlap(
                $property,
                $event['start'],
                $event['end'],
            )) {
                ++$skipped;
                continue;
            }

            $existing = $this->availabilityRepository->findOneByPropertyAndExternalUid($property, $uid);

            if ($existing !== null) {
                $existing->setAvailableDate($event['start']);
                $existing->setIsAvailable(false);
                $existing->setSource('ical');
                $existing->setReason($event['summary'] ?? 'Synchronisation iCal');
            } else {
                $this->blockEventDays($property, $event);
                ++$imported;
            }
        }

        $removed = $this->removeStaleIcalBlocks($property, $activeUids);

        $sync->setLastSyncAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return ['imported' => $imported, 'skipped' => $skipped, 'removed' => $removed];
    }

    /**
     * @return list<array{uid: string, start: \DateTimeImmutable, end: \DateTimeImmutable, summary: ?string}>
     */
    private function parseEvents(string $content): array
    {
        $events = [];
        $blocks = preg_split('/BEGIN:VEVENT/', $content) ?: [];

        foreach ($blocks as $block) {
            if (!str_contains($block, 'END:VEVENT')) {
                continue;
            }

            $uid = $this->extractValue($block, 'UID');
            $dtStart = $this->extractDateValue($block, 'DTSTART');
            $dtEnd = $this->extractDateValue($block, 'DTEND');

            if ($uid === null || $dtStart === null || $dtEnd === null || $dtStart >= $dtEnd) {
                continue;
            }

            $events[] = [
                'uid' => $uid,
                'start' => $dtStart,
                'end' => $dtEnd,
                'summary' => $this->extractValue($block, 'SUMMARY'),
            ];
        }

        return $events;
    }

    /**
     * @param array{uid: string, start: \DateTimeImmutable, end: \DateTimeImmutable, summary: ?string} $event
     */
    private function blockEventDays(Property $property, array $event): void
    {
        $current = $event['start'];

        while ($current < $event['end']) {
            $existing = $this->availabilityRepository->findOneByPropertyAndDate($property, $current);

            if ($existing !== null && $existing->getSource() === 'manual') {
                $current = $current->modify('+1 day');
                continue;
            }

            if ($existing !== null) {
                $existing->setIsAvailable(false);
                $existing->setSource('ical');
                $existing->setExternalUid($event['uid']);
                $existing->setReason($event['summary'] ?? 'Synchronisation iCal');
            } else {
                $availability = new PropertyAvailability();
                $availability->setProperty($property);
                $availability->setAvailableDate($current);
                $availability->setIsAvailable(false);
                $availability->setSource('ical');
                $availability->setExternalUid($event['uid']);
                $availability->setReason($event['summary'] ?? 'Synchronisation iCal');
                $this->entityManager->persist($availability);
            }

            $current = $current->modify('+1 day');
        }
    }

    /**
     * @param list<string> $activeUids
     */
    private function removeStaleIcalBlocks(Property $property, array $activeUids): int
    {
        $icalBlocks = $this->availabilityRepository->findIcalBlocksByProperty($property);
        $removed = 0;

        foreach ($icalBlocks as $block) {
            $uid = $block->getExternalUid();
            if ($uid !== null && !\in_array($uid, $activeUids, true)) {
                $this->entityManager->remove($block);
                ++$removed;
            }
        }

        return $removed;
    }

    private function extractValue(string $block, string $key): ?string
    {
        if (preg_match('/' . $key . '(?:;[^:]*)?:(.+)/', $block, $matches) !== 1) {
            return null;
        }

        return trim(str_replace('\\n', "\n", $matches[1]));
    }

    private function extractDateValue(string $block, string $key): ?\DateTimeImmutable
    {
        $raw = $this->extractValue($block, $key);
        if ($raw === null) {
            return null;
        }

        $raw = preg_replace('/^.*:(\d{8}).*$/', '$1', $raw) ?? $raw;
        $date = \DateTimeImmutable::createFromFormat('Ymd', substr($raw, 0, 8));

        return $date !== false ? $date : null;
    }
}
