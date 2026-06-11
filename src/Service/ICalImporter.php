<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PropertyICalSync;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ICalImporter
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AvailabilityManager $availabilityManager,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function import(PropertyICalSync $sync): int
    {
        $property = $sync->getProperty();
        $url = $sync->getICalUrl();

        if ($property === null || $url === null) {
            return 0;
        }

        $content = $this->httpClient->request('GET', $url)->getContent();
        $events = $this->parseEvents($content);

        $count = 0;
        foreach ($events as [$start, $end]) {
            // En iCal, DTEND est EXCLUSIF (jour de départ) -> on bloque jusqu'à la veille
            $lastNight = $end->modify('-1 day');
            if ($lastNight >= $start) {
                $this->availabilityManager->block($property, $start, $lastNight);
                ++$count;
            }
        }

        $sync->setLastSyncAt(new \DateTimeImmutable());
        $this->em->flush();

        return $count;
    }

    /**
     * @return list<array{0:\DateTimeImmutable,1:\DateTimeImmutable}>
     */
    private function parseEvents(string $ics): array
    {
        // Déplier les lignes repliées (RFC 5545 : une continuation commence par espace/tab)
        $ics = preg_replace("/\r\n[ \t]/", '', $ics) ?? $ics;
        $lines = preg_split("/\r\n|\n|\r/", $ics) ?: [];

        $events = [];
        $start = null;
        $end = null;
        $inEvent = false;

        foreach ($lines as $line) {
            if (str_starts_with($line, 'BEGIN:VEVENT')) {
                $inEvent = true;
                $start = null;
                $end = null;
            } elseif (str_starts_with($line, 'END:VEVENT')) {
                if ($start instanceof \DateTimeImmutable && $end instanceof \DateTimeImmutable) {
                    $events[] = [$start, $end];
                }
                $inEvent = false;
            } elseif ($inEvent && str_starts_with($line, 'DTSTART')) {
                $start = $this->parseDate($line);
            } elseif ($inEvent && str_starts_with($line, 'DTEND')) {
                $end = $this->parseDate($line);
            }
        }

        return $events;
    }

    private function parseDate(string $line): ?\DateTimeImmutable
    {
        // ex : "DTSTART;VALUE=DATE:20260710" ou "DTSTART:20260710T140000Z"
        $value = substr($line, strpos($line, ':') + 1);
        if (preg_match('/(\d{4})(\d{2})(\d{2})/', $value, $m)) {
            return (new \DateTimeImmutable())->setDate((int) $m[1], (int) $m[2], (int) $m[3])->setTime(0, 0);
        }

        return null;
    }
}