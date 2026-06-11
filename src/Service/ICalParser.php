<?php

declare(strict_types=1);

namespace App\Service;

final class ICalParser
{
    public function parseEvents(string $content): array
    {
        $events = [];
        $inEvent = false;
        $start = null;
        $end = null;

        foreach ($this->unfold($content) as $line) {
            $upper = strtoupper($line);

            if ($upper === 'BEGIN:VEVENT') {
                $inEvent = true;
                $start = null;
                $end = null;
                continue;
            }

            if ($upper === 'END:VEVENT') {
                if ($inEvent && $start instanceof \DateTimeImmutable) {
                    $events[] = ['start' => $start, 'end' => $end ?? $start->modify('+1 day')];
                }
                $inEvent = false;
                continue;
            }

            if (!$inEvent) {
                continue;
            }

            [$name, $value] = $this->splitLine($line);
            if ($name === 'DTSTART') {
                $start = $this->parseDate($value);
            } elseif ($name === 'DTEND') {
                $end = $this->parseDate($value);
            }
        }

        return $events;
    }

    private function unfold(string $content): array
    {
        $raw = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $lines = [];

        foreach ($raw as $line) {
            if ($line === '') {
                continue;
            }
            if (($line[0] === ' ' || $line[0] === "\t") && $lines !== []) {
                $lines[\count($lines) - 1] .= substr($line, 1);
            } else {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function splitLine(string $line): array
    {
        $colon = strpos($line, ':');
        if ($colon === false) {
            return ['', ''];
        }

        $left = substr($line, 0, $colon);
        $value = trim(substr($line, $colon + 1));
        $semicolon = strpos($left, ';');
        $name = strtoupper($semicolon === false ? $left : substr($left, 0, $semicolon));

        return [$name, $value];
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        if (preg_match('/^\d{8}$/', $value) === 1) {
            $date = \DateTimeImmutable::createFromFormat('Ymd', $value);

            return $date !== false ? $date->setTime(0, 0) : null;
        }

        if (preg_match('/^\d{8}T\d{6}Z?$/', $value) === 1) {
            $format = str_ends_with($value, 'Z') ? 'Ymd\THis\Z' : 'Ymd\THis';
            $date = \DateTimeImmutable::createFromFormat($format, $value);

            return $date !== false ? $date->setTime(0, 0) : null;
        }

        return null;
    }
}
