<?php

declare(strict_types=1);

namespace App\Service\ICal;

use App\Entity\Property;
use App\Repository\ReservationRepository;
use App\Repository\UnavailabilityRepository;

final class ICalExporter
{
    private const PRODID = '-//Location//Calendrier//FR';

    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly UnavailabilityRepository $unavailabilityRepository,
    ) {
    }

    public function export(Property $property): string
    {
        $rangeStart = new \DateTimeImmutable('today');
        $rangeEnd = $rangeStart->modify('+2 years');

        $reservations = $this->reservationRepository->findOverlapping(
            $property,
            $rangeStart,
            $rangeEnd,
            ['confirmed'],
        );
        $unavailabilities = $this->unavailabilityRepository->findForPropertyBetween(
            $property,
            $rangeStart,
            $rangeEnd,
        );

        $now = (new \DateTimeImmutable())->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . self::PRODID,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($reservations as $reservation) {
            $start = $reservation->getCheckinDate();
            $end = $reservation->getCheckoutDate();
            if (!$start instanceof \DateTimeImmutable || !$end instanceof \DateTimeImmutable) {
                continue;
            }

            $lines = array_merge($lines, $this->buildEvent(
                'reservation-' . $reservation->getId(),
                $start,
                $end,
                'Réservé',
                $now,
            ));
        }

        foreach ($unavailabilities as $unavailability) {
            $start = $unavailability->getStartDate();
            $end = $unavailability->getEndDate();
            if (!$start instanceof \DateTimeImmutable || !$end instanceof \DateTimeImmutable) {
                continue;
            }

            $lines = array_merge($lines, $this->buildEvent(
                'unavailability-' . $unavailability->getId(),
                $start,
                $end,
                'Indisponible',
                $now,
            ));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * @return list<string>
     */
    private function buildEvent(
        string $uid,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        string $summary,
        string $stamp,
    ): array {
        return [
            'BEGIN:VEVENT',
            'UID:' . $uid . '@location',
            'DTSTAMP:' . $stamp,
            'DTSTART;VALUE=DATE:' . $start->format('Ymd'),
            'DTEND;VALUE=DATE:' . $end->format('Ymd'),
            'SUMMARY:' . $summary,
            'END:VEVENT',
        ];
    }
}
