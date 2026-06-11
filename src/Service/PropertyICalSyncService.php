<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PropertyAvailability;
use App\Entity\PropertyICalSync;
use App\Exception\PropertyICalSyncException;
use App\Repository\PropertyAvailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PropertyICalSyncService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient,
        private PropertyAvailabilityRepository $propertyAvailabilityRepository,
    ) {
    }

    public function sync(PropertyICalSync $sync): int
    {
        $url = $sync->getICalUrl();
        if ($url === null || trim($url) === '') {
            throw new PropertyICalSyncException('L\'URL iCal est manquante.');
        }

        try {
            $response = $this->httpClient->request('GET', $this->normalizeUrl($url), [
                'timeout' => 15,
                'max_redirects' => 5,
            ]);
            $content = $response->getContent();
        } catch (ExceptionInterface $e) {
            throw new PropertyICalSyncException('Impossible de recuperer le calendrier iCal.', 0, $e);
        }

        $property = $sync->getProperty();
        if ($property === null) {
            throw new PropertyICalSyncException('Le logement du calendrier iCal est introuvable.');
        }

        $blockedDateKeys = $this->parseBlockedDateKeys($content);
        $existingEntries = $this->propertyAvailabilityRepository->findImportedForSync($sync);
        $existingByDate = [];

        foreach ($existingEntries as $entry) {
            $date = $entry->getAvailableDate();
            if ($date === null) {
                continue;
            }

            $existingByDate[$date->format('Y-m-d')][] = $entry;
        }

        $blockedLookup = array_fill_keys($blockedDateKeys, true);

        foreach ($existingByDate as $dateKey => $entries) {
            if (isset($blockedLookup[$dateKey])) {
                $keeper = array_shift($entries);
                if ($keeper instanceof PropertyAvailability) {
                    $keeper->setIsAvailable(false);
                    $keeper->setPriceOverride(null);
                    $keeper->setMinimumStay(null);
                    $keeper->setSource('ical_import');
                    $keeper->setPropertyICalSync($sync);
                }

                foreach ($entries as $extraEntry) {
                    $this->entityManager->remove($extraEntry);
                }

                continue;
            }

            foreach ($entries as $entry) {
                $this->entityManager->remove($entry);
            }
        }

        foreach ($blockedDateKeys as $dateKey) {
            if (isset($existingByDate[$dateKey])) {
                continue;
            }

            $entry = new PropertyAvailability();
            $entry->setProperty($property);
            $entry->setAvailableDate(new \DateTimeImmutable($dateKey));
            $entry->setIsAvailable(false);
            $entry->setPriceOverride(null);
            $entry->setMinimumStay(null);
            $entry->setSource('ical_import');
            $entry->setPropertyICalSync($sync);
            $this->entityManager->persist($entry);
        }

        $sync->setLastSyncAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return count($blockedDateKeys);
    }

    /**
     * @return list<string>
     */
    private function parseBlockedDateKeys(string $content): array
    {
        $lines = $this->unfoldLines($content);
        $blocked = [];
        $inEvent = false;
        $status = null;
        $startValue = null;
        $startParams = [];
        $endValue = null;
        $endParams = [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if ($trimmedLine === 'BEGIN:VEVENT') {
                $inEvent = true;
                $status = null;
                $startValue = null;
                $startParams = [];
                $endValue = null;
                $endParams = [];

                continue;
            }

            if ($trimmedLine === 'END:VEVENT') {
                if ($inEvent && $startValue !== null && strtoupper((string) $status) !== 'CANCELLED') {
                    foreach ($this->expandEventDates($startValue, $startParams, $endValue, $endParams) as $dateKey) {
                        $blocked[$dateKey] = true;
                    }
                }

                $inEvent = false;

                continue;
            }

            if (!$inEvent || !str_contains($line, ':')) {
                continue;
            }

            $propertyLine = $this->parsePropertyLine($line);

            if ($propertyLine['name'] === 'DTSTART') {
                $startValue = $propertyLine['value'];
                $startParams = $propertyLine['params'];
            } elseif ($propertyLine['name'] === 'DTEND') {
                $endValue = $propertyLine['value'];
                $endParams = $propertyLine['params'];
            } elseif ($propertyLine['name'] === 'STATUS') {
                $status = $propertyLine['value'];
            }
        }

        $dateKeys = array_keys($blocked);
        sort($dateKeys);

        return $dateKeys;
    }

    /**
     * @return array{name: string, params: array<string, string>, value: string}
     */
    private function parsePropertyLine(string $line): array
    {
        [$rawProperty, $value] = explode(':', $line, 2);
        $segments = explode(';', $rawProperty);
        $name = strtoupper(array_shift($segments) ?? '');
        $params = [];

        foreach ($segments as $segment) {
            if (!str_contains($segment, '=')) {
                continue;
            }

            [$paramName, $paramValue] = explode('=', $segment, 2);
            $params[strtoupper($paramName)] = strtoupper($paramValue);
        }

        return [
            'name' => $name,
            'params' => $params,
            'value' => trim($value),
        ];
    }

    /**
     * @return list<string>
     */
    private function expandEventDates(
        string $startValue,
        array $startParams,
        ?string $endValue,
        array $endParams,
    ): array {
        $startDate = $this->parseICalDate($startValue, $startParams);
        $endDateExclusive = $endValue !== null
            ? $this->parseICalEndDate($startDate, $endValue, $endParams)
            : $startDate->modify('+1 day');

        if ($endDateExclusive <= $startDate) {
            $endDateExclusive = $startDate->modify('+1 day');
        }

        $dates = [];
        $cursor = $startDate;

        while ($cursor < $endDateExclusive) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }

        return $dates;
    }

    private function parseICalDate(string $value, array $params): \DateTimeImmutable
    {
        $isDateOnly = ($params['VALUE'] ?? '') === 'DATE' || preg_match('/^\d{8}$/', $value) === 1;

        if ($isDateOnly) {
            $date = \DateTimeImmutable::createFromFormat('!Ymd', $value);
            if ($date instanceof \DateTimeImmutable) {
                return $date;
            }
        }

        return $this->parseICalDateTime($value)->setTime(0, 0);
    }

    private function parseICalEndDate(
        \DateTimeImmutable $startDate,
        string $value,
        array $params,
    ): \DateTimeImmutable {
        $isDateOnly = ($params['VALUE'] ?? '') === 'DATE' || preg_match('/^\d{8}$/', $value) === 1;

        if ($isDateOnly) {
            $date = \DateTimeImmutable::createFromFormat('!Ymd', $value);
            if ($date instanceof \DateTimeImmutable) {
                return $date;
            }
        }

        $dateTime = $this->parseICalDateTime($value);
        $endDateExclusive = $dateTime->setTime(0, 0);

        if ($dateTime->format('H:i:s') !== '00:00:00') {
            $endDateExclusive = $endDateExclusive->modify('+1 day');
        }

        return $endDateExclusive > $startDate ? $endDateExclusive : $startDate->modify('+1 day');
    }

    private function parseICalDateTime(string $value): \DateTimeImmutable
    {
        $utcDate = \DateTimeImmutable::createFromFormat('!Ymd\THis\Z', $value, new \DateTimeZone('UTC'));
        if ($utcDate instanceof \DateTimeImmutable) {
            return $utcDate;
        }

        $localDate = \DateTimeImmutable::createFromFormat('!Ymd\THis', $value);
        if ($localDate instanceof \DateTimeImmutable) {
            return $localDate;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            throw new PropertyICalSyncException('Le calendrier iCal contient une date invalide.', 0, $e);
        }
    }

    /**
     * @return list<string>
     */
    private function unfoldLines(string $content): array
    {
        $rawLines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $lines = [];

        foreach ($rawLines as $rawLine) {
            if ($rawLine === '') {
                continue;
            }

            if (($rawLine[0] === ' ' || $rawLine[0] === "\t") && $lines !== []) {
                $lines[array_key_last($lines)] .= substr($rawLine, 1);
                continue;
            }

            $lines[] = $rawLine;
        }

        return $lines;
    }

    private function normalizeUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            throw new PropertyICalSyncException('L\'URL iCal est invalide.');
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = $parts['port'] ?? null;

        if (in_array($host, ['localhost', '127.0.0.1'], true) && ($port === 8089 || $port === null)) {
            $parts['scheme'] = 'http';
            $parts['host'] = 'php';
            $parts['port'] = 8000;
        }

        $normalizedUrl = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '');

        if (isset($parts['port'])) {
            $normalizedUrl .= ':' . $parts['port'];
        }

        $normalizedUrl .= $parts['path'] ?? '';

        if (isset($parts['query'])) {
            $normalizedUrl .= '?' . $parts['query'];
        }

        if (isset($parts['fragment'])) {
            $normalizedUrl .= '#' . $parts['fragment'];
        }

        return $normalizedUrl;
    }
}
