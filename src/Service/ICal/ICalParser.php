<?php

declare(strict_types=1);

namespace App\Service\ICal;

use DateTimeImmutable;
use DateTimeInterface;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

/**
 * Analyse un flux iCal distant (RFC 5545) et en extrait les périodes occupées.
 * S'appuie sur sabre/vobject (mode tolérant) pour absorber les variations des
 * flux réels (Airbnb, Google, Outlook) : VALUE=DATE vs DATE-TIME, VTIMEZONE,
 * propriétés X-, lignes pliées.
 *
 * Chaque période est renvoyée en intervalle demi-ouvert [start, end) cohérent
 * avec le reste du moteur : `end` est le jour de départ (non bloqué).
 */
final class ICalParser
{
    /**
     * @return list<array{start: DateTimeImmutable, end: DateTimeImmutable}>
     */
    public function parse(string $ics): array
    {
        $document = Reader::read($ics, Reader::OPTION_FORGIVING | Reader::OPTION_IGNORE_INVALID_LINES);
        if (!$document instanceof VCalendar) {
            return [];
        }

        $ranges = [];
        foreach ($document->VEVENT ?? [] as $event) {
            $dtStart = $event->DTSTART ?? null;
            if ($dtStart === null) {
                continue;
            }

            $start = $this->toDate($dtStart->getDateTime());
            $dtEnd = $event->DTEND ?? null;
            $end = $dtEnd !== null ? $this->toDate($dtEnd->getDateTime()) : $start->modify('+1 day');

            // Sécurité : une période doit couvrir au moins une nuit.
            if ($end <= $start) {
                $end = $start->modify('+1 day');
            }

            $ranges[] = ['start' => $start, 'end' => $end];
        }

        return $ranges;
    }

    private function toDate(DateTimeInterface $dateTime): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($dateTime)->setTime(0, 0);
    }
}
