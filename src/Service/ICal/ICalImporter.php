<?php

declare(strict_types=1);

namespace App\Service\ICal;

use App\Entity\PropertyICalSync;
use App\Entity\Unavailability;
use App\Repository\UnavailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ICalImporter
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly UnavailabilityRepository $unavailabilityRepository,
    ) {
    }

    public function import(PropertyICalSync $sync): int
    {
        $property = $sync->getProperty();
        $url = $sync->getICalUrl();
        if ($property === null || $url === null || $url === '') {
            return 0;
        }

        $content = $this->httpClient->request('GET', $url)->getContent();
        $events = $this->parse($content);

        $existing = [];
        foreach ($this->unavailabilityRepository->findBy(['property' => $property, 'source' => 'ical_import']) as $unavailability) {
            $uid = $unavailability->getExternalUid();
            if ($uid !== null) {
                $existing[$uid] = $unavailability;
            }
        }

        $seen = [];
        foreach ($events as $event) {
            $uid = $event['uid'];
            $seen[$uid] = true;

            $unavailability = $existing[$uid] ?? null;
            if ($unavailability === null) {
                $unavailability = new Unavailability();
                $unavailability->setProperty($property);
                $unavailability->setSource('ical_import');
                $unavailability->setExternalUid($uid);
                $this->em->persist($unavailability);
            }

            $unavailability->setStartDate($event['start']);
            $unavailability->setEndDate($event['end']);
        }

        foreach ($existing as $uid => $unavailability) {
            if (!isset($seen[$uid])) {
                $this->em->remove($unavailability);
            }
        }

        $sync->setLastSyncAt(new \DateTimeImmutable());
        $this->em->flush();

        return count($seen);
    }

    /**
     * @return list<array{uid: string, start: \DateTimeImmutable, end: \DateTimeImmutable}>
     */
    private function parse(string $content): array
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/\n[ \t]/', '', $content) ?? $content;
        $lines = explode("\n", $content);

        $events = [];
        $current = null;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === 'BEGIN:VEVENT') {
                $current = ['uid' => null, 'start' => null, 'end' => null];
                continue;
            }
            if ($line === 'END:VEVENT') {
                if (
                    is_array($current)
                    && $current['uid'] !== null
                    && $current['start'] instanceof \DateTimeImmutable
                    && $current['end'] instanceof \DateTimeImmutable
                    && $current['start'] < $current['end']
                ) {
                    $events[] = $current;
                }
                $current = null;
                continue;
            }
            if (!is_array($current)) {
                continue;
            }

            [$name, $value] = $this->splitLine($line);
            if ($name === null) {
                continue;
            }
            $key = strtoupper(explode(';', $name)[0]);

            if ($key === 'UID') {
                $current['uid'] = $value;
            } elseif ($key === 'DTSTART') {
                $current['start'] = $this->parseDate($value);
            } elseif ($key === 'DTEND') {
                $current['end'] = $this->parseDate($value);
            }
        }

        return $events;
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function splitLine(string $line): array
    {
        $pos = strpos($line, ':');
        if ($pos === false) {
            return [null, ''];
        }

        return [substr($line, 0, $pos), substr($line, $pos + 1)];
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        $datePart = substr($value, 0, 8);
        $date = \DateTimeImmutable::createFromFormat('!Ymd', $datePart);

        return $date !== false ? $date : null;
    }
}
