<?php

declare(strict_types=1);

namespace App\Service\ICal;

final readonly class ICalImportParser
{
    /**
     * @return list<array{start: \DateTimeImmutable, end: \DateTimeImmutable}>
     */
    public function parse(string $content): array
    {
        $events = [];
        $current = null;

        foreach ($this->unfoldLines($content) as $line) {
            if ($line === 'BEGIN:VEVENT') {
                $current = [];
                continue;
            }

            if ($line === 'END:VEVENT') {
                if (is_array($current) && isset($current['DTSTART'])) {
                    $start = $this->parseDate($current['DTSTART']);
                    $end = isset($current['DTEND']) ? $this->parseDate($current['DTEND']) : $start->modify('+1 day');
                    if ($end > $start) {
                        $events[] = [
                            'start' => $start,
                            'end' => $end,
                        ];
                    }
                }

                $current = null;
                continue;
            }

            if ($current === null || !str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $name = strtoupper(explode(';', $name, 2)[0]);
            if ($name === 'DTSTART' || $name === 'DTEND') {
                $current[$name] = $value;
            }
        }

        return $events;
    }

    /**
     * @return list<string>
     */
    private function unfoldLines(string $content): array
    {
        $rawLines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $lines = [];

        foreach ($rawLines as $line) {
            if (($line[0] ?? '') === ' ' || ($line[0] ?? '') === "\t") {
                $last = array_key_last($lines);
                if ($last !== null) {
                    $lines[$last] .= substr($line, 1);
                }
                continue;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    private function parseDate(string $value): \DateTimeImmutable
    {
        if (!preg_match('/^(\d{8})/', $value, $matches)) {
            throw new \DomainException('Le flux iCal contient une date illisible.');
        }

        $date = \DateTimeImmutable::createFromFormat('!Ymd', $matches[1]);
        if (!$date instanceof \DateTimeImmutable) {
            throw new \DomainException('Le flux iCal contient une date invalide.');
        }

        return $date;
    }
}
