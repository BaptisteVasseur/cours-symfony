<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PropertyAvailability;
use App\Entity\PropertyICalSync;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ICalImportService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly PropertyAvailabilityRepository $availabilities,
        private readonly ReservationRepository $reservations,
    ) {
    }

    public function sync(PropertyICalSync $sync): int
    {
        $property = $sync->getProperty();
        $url = $sync->getICalUrl();
        if ($property === null || $url === null) {
            return 0;
        }

        $content = $this->httpClient->request('GET', $url)->getContent();
        $blocked = 0;

        foreach ($this->parsePeriods($content) as [$start, $end]) {
            for ($day = $start; $day < $end; $day = $day->modify('+1 day')) {
                if ($this->reservations->hasConfirmedOverlap($property, $day, $day->modify('+1 day'))) {
                    continue;
                }

                if ($this->availabilities->findOneByPropertyAndDate($property, $day) !== null) {
                    continue;
                }

                $availability = (new PropertyAvailability())
                    ->setProperty($property)
                    ->setAvailableDate($day)
                    ->setIsAvailable(false);
                $this->em->persist($availability);
                ++$blocked;
            }
        }

        $sync->setLastSyncAt(new \DateTimeImmutable());
        $this->em->flush();

        return $blocked;
    }

    /**
     * @return list<array{0: \DateTimeImmutable, 1: \DateTimeImmutable}>
     */
    private function parsePeriods(string $ics): array
    {
        $periods = [];
        $start = null;
        $end = null;

        foreach (preg_split('/\r\n|\r|\n/', $ics) ?: [] as $line) {
            if (str_starts_with($line, 'DTSTART')) {
                $start = $this->parseDate($line);
            } elseif (str_starts_with($line, 'DTEND')) {
                $end = $this->parseDate($line);
            } elseif (str_starts_with($line, 'END:VEVENT')) {
                if ($start !== null && $end !== null && $start < $end) {
                    $periods[] = [$start, $end];
                }
                $start = $end = null;
            }
        }

        return $periods;
    }

    private function parseDate(string $line): ?\DateTimeImmutable
    {
        $value = substr($line, (int) strpos($line, ':') + 1);
        if (!preg_match('/(\d{8})/', $value, $matches)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Ymd', $matches[1]);

        return $date !== false ? $date : null;
    }
}
