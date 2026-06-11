<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ICalEvent;

final class ICalImportService
{

    public function parse(string $ics): array
    {
        $lines = $this->unfold($ics);

        $events = [];
        $inEvent = false;
        $current = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === 'BEGIN:VEVENT') {
                $inEvent = true;
                $current = [];
                continue;
            }

            if ($trimmed === 'END:VEVENT') {
                $inEvent = false;
                $event = $this->buildEvent($current);
                if ($event !== null) {
                    $events[] = $event;
                }
                continue;
            }

            if (!$inEvent) {
                continue;
            }

            [$name, $value] = $this->splitProperty($line);
            if ($name !== null) {
                $current[$name] = $value;
            }
        }

        return $events;
    }
    private function unfold(string $ics): array
    {
        $raw = preg_split('/\r\n|\n|\r/', $ics) ?: [];

        $logical = [];
        foreach ($raw as $line) {
            if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t") && $logical !== []) {
                $logical[count($logical) - 1] .= substr($line, 1);
            } else {
                $logical[] = $line;
            }
        }

        return $logical;
    }

    private function splitProperty(string $line): array
    {
        $colon = strpos($line, ':');
        if ($colon === false) {
            return [null, ''];
        }

        $left = substr($line, 0, $colon);
        $value = substr($line, $colon + 1);
        $name = strtoupper(explode(';', $left)[0]);

        return [$name, $value];
    }

    private function buildEvent(array $props): ?ICalEvent
    {
        if (!isset($props['DTSTART'])) {
            return null;
        }

        $start = $this->parseDate($props['DTSTART']);
        if ($start === null) {
            return null;
        }

        $end = isset($props['DTEND']) ? $this->parseDate($props['DTEND']) : null;
        if ($end === null || $end <= $start) {
            $end = $start->modify('+1 day');
        }

        return new ICalEvent(
            uid: $props['UID'] ?? null,
            start: $start,
            end: $end,
            summary: $props['SUMMARY'] ?? null,
        );
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);

        if (str_contains($value, 'T')) {
            $value = explode('T', $value)[0];
        }

        if (preg_match('/^\d{8}$/', $value) !== 1) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Ymd', $value);

        return $date instanceof \DateTimeImmutable ? $date->setTime(0, 0) : null;
    }
}
