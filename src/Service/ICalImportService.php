<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AvailabilityBlock;
use App\Entity\Property;
use App\Enum\BlockReason;
use App\Repository\AvailabilityBlockRepository;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class ICalImportService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AvailabilityBlockRepository $availabilityBlockRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly LoggerInterface $logger,
        private readonly RealtimePublisher $realtimePublisher,
    ) {
    }

    /**
     * @return array{created: int, updated: int, deleted: int, skipped: int}
     */
    public function parseAndSync(Property $property, string $icsContent): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0];
        $remoteUids = [];

        foreach ($this->extractEvents($icsContent) as $event) {
            $uid = $this->extractValue($event, 'UID');
            $start = $this->parseDate($this->extractValue($event, 'DTSTART'));
            $end = $this->parseDate($this->extractValue($event, 'DTEND'));

            if ($uid === null || $uid === '' || $start === null || $end === null || $start >= $end) {
                ++$stats['skipped'];
                continue;
            }

            $remoteUids[$uid] = true;
            $block = $this->availabilityBlockRepository->findOneImportedByUid($property, $uid);

            if ($block !== null && $block->getStartDate()?->format('Y-m-d') === $start->format('Y-m-d') && $block->getEndDate()?->format('Y-m-d') === $end->format('Y-m-d')) {
                ++$stats['skipped'];
                continue;
            }

            if ($this->bookingRepository->countOverlappingConfirmed($property, $start, $end) > 0) {
                $this->logger->warning('Bloc iCal ignore car il chevauche une reservation confirmee.', [
                    'property' => (string) $property->getId(),
                    'uid' => $uid,
                    'start' => $start->format('Y-m-d'),
                    'end' => $end->format('Y-m-d'),
                ]);
                ++$stats['skipped'];
                continue;
            }

            if ($block === null) {
                $block = new AvailabilityBlock();
                $block->setProperty($property);
                $block->setReason(BlockReason::ICAL_IMPORT);
                $block->setExternalUid($uid);
                $block->setNotes('Import iCal');
                $this->entityManager->persist($block);
                ++$stats['created'];
            } else {
                ++$stats['updated'];
            }

            $block->setStartDate($start);
            $block->setEndDate($end);
        }

        foreach ($this->availabilityBlockRepository->findImportedByProperty($property) as $block) {
            $uid = $block->getExternalUid();
            if ($uid === null || !isset($remoteUids[$uid])) {
                $this->entityManager->remove($block);
                ++$stats['deleted'];
            }
        }

        $this->entityManager->flush();

        if ($stats['created'] > 0 || $stats['updated'] > 0 || $stats['deleted'] > 0) {
            $this->realtimePublisher->publishPropertyAvailabilityChanged($property, [
                'source' => 'ical_import',
                'stats' => $stats,
            ]);
        }

        return $stats;
    }

    /**
     * @return list<string>
     */
    private function extractEvents(string $icsContent): array
    {
        $content = preg_replace("/\r\n[ \t]|\n[ \t]|\r[ \t]/", '', $icsContent) ?? $icsContent;
        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $content, $matches);

        return $matches[1] ?? [];
    }

    private function extractValue(string $event, string $name): ?string
    {
        if (!preg_match('/^'.preg_quote($name, '/').'(?:;[^:]*)?:(.+)$/mi', $event, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || !preg_match('/^(\d{8})/', trim($value), $matches)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Ymd', $matches[1]);

        return $date !== false ? $date : null;
    }
}
