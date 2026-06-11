<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Parseur iCal minimal pour l'import de calendriers externes (Partie F) :
 * déplie les lignes (RFC 5545), extrait les VEVENT et leurs UID / SUMMARY /
 * DTSTART / DTEND. Les événements sans UID ou sans dates sont ignorés.
 */
final class ICalParser
{
    /**
     * `allDay` vaut true quand DTSTART est une date pure (VALUE=DATE) : la
     * sémantique est alors « nuitées occupées » et l'appelant doit appliquer
     * les heures d'arrivée/départ du logement.
     *
     * @return list<array{uid: string, summary: ?string, start: \DateTimeImmutable, end: \DateTimeImmutable, allDay: bool}>
     */
    public function parse(string $content): array
    {
        // Dépliage : une ligne continuée commence par un espace ou une tabulation
        $unfolded = preg_replace("/(?:\r\n|\n|\r)[ \t]/", '', $content) ?? $content;
        $lines = preg_split("/\r\n|\n|\r/", $unfolded) ?: [];

        $events = [];
        $current = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === 'BEGIN:VEVENT') {
                $current = ['uid' => null, 'summary' => null, 'start' => null, 'end' => null, 'allDay' => false];
                continue;
            }
            if ($line === 'END:VEVENT') {
                if ($current !== null && $current['uid'] !== null && $current['start'] !== null && $current['end'] !== null && $current['end'] > $current['start']) {
                    $events[] = $current;
                }
                $current = null;
                continue;
            }
            if ($current === null || !str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $params = '';
            if (str_contains($name, ';')) {
                [$name, $params] = explode(';', $name, 2);
            }

            switch (strtoupper($name)) {
                case 'UID':
                    $current['uid'] = $value;
                    break;
                case 'SUMMARY':
                    $current['summary'] = $this->unescape($value);
                    break;
                case 'DTSTART':
                    [$current['start'], $current['allDay']] = $this->parseDate($value, $params);
                    break;
                case 'DTEND':
                    [$current['end']] = $this->parseDate($value, $params);
                    break;
            }
        }

        return $events;
    }

    /**
     * @return array{0: ?\DateTimeImmutable, 1: bool} date analysée + indicateur « date pure »
     */
    private function parseDate(string $value, string $params): array
    {
        $value = trim($value);

        // Date seule (VALUE=DATE ou format 8 chiffres) : minuit local
        if (str_contains(strtoupper($params), 'VALUE=DATE') || preg_match('/^\d{8}$/', $value) === 1) {
            $date = \DateTimeImmutable::createFromFormat('!Ymd', substr($value, 0, 8));

            return [$date !== false ? $date : null, true];
        }

        // DATE-TIME : 20260710T150000 ou 20260710T150000Z (UTC)
        if (preg_match('/^(\d{8}T\d{6})(Z?)$/', $value, $m) === 1) {
            $date = \DateTimeImmutable::createFromFormat(
                'Ymd\THis',
                $m[1],
                $m[2] === 'Z' ? new \DateTimeZone('UTC') : null,
            );

            return [$date !== false ? $date : null, false];
        }

        return [null, false];
    }

    private function unescape(string $value): string
    {
        return str_replace(['\n', '\N', '\,', '\;', '\\\\'], ["\n", "\n", ',', ';', '\\'], $value);
    }
}
