<?php

declare(strict_types=1);

namespace App\Service;

final class ICalImportService
{
    /**
     * @return list<array{start: \DateTimeImmutable, end: \DateTimeImmutable}>
     */
    public function parse(string $icalContent): array
    {
        $normalized = str_replace("\r\n ", '', str_replace("\n ", '', $icalContent));
        $lines = preg_split('/\r\n|\r|\n/', $normalized) ?: [];
        $events = [];
        $inEvent = false;
        $dtstart = null;
        $dtend = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === 'BEGIN:VEVENT') {
                $inEvent = true;
                $dtstart = null;
                $dtend = null;
                continue;
            }

            if ($line === 'END:VEVENT') {
                if ($inEvent && $dtstart instanceof \DateTimeImmutable && $dtend instanceof \DateTimeImmutable && $dtstart < $dtend) {
                    $events[] = ['start' => $dtstart, 'end' => $dtend];
                }
                $inEvent = false;
                continue;
            }

            if (!$inEvent) {
                continue;
            }

            if (str_starts_with($line, 'DTSTART')) {
                $dtstart = $this->parseDateTime($line);
            }

            if (str_starts_with($line, 'DTEND')) {
                $dtend = $this->parseDateTime($line);
            }
        }

        return $events;
    }

    private function parseDateTime(string $line): ?\DateTimeImmutable
    {
        if (!preg_match('/:([\dTZ]+)$/', $line, $matches)) {
            return null;
        }

        $value = $matches[1];

        if (preg_match('/^\d{8}$/', $value)) {
            $date = \DateTimeImmutable::createFromFormat('!Ymd', $value);

            return $date !== false ? $date : null;
        }

        if (preg_match('/^\d{8}T\d{6}Z?$/', $value)) {
            $format = str_ends_with($value, 'Z') ? '!Ymd\THis\Z' : '!Ymd\THis';
            $date = \DateTimeImmutable::createFromFormat($format, $value);

            return $date !== false ? $date->setTime(0, 0) : null;
        }

        return null;
    }
}
