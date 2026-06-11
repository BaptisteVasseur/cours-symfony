<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;

final class ICalExportService
{
    public function __construct(
        private readonly ReservationRepository $reservations,
    ) {
    }

    public function export(Property $property): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clone Airbnb//FR',
        ];

        foreach ($this->reservations->findConfirmedForProperty($property) as $reservation) {
            array_push($lines, ...$this->event($property, $reservation));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * @return list<string>
     */
    private function event(Property $property, Reservation $reservation): array
    {
        $nights = (int) $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days;

        return [
            'BEGIN:VEVENT',
            sprintf('UID:res-%s@clone-airbnb.local', $reservation->getId()),
            sprintf('SUMMARY:%s — %s', $this->escape($property->getTitle() ?? ''), $this->escape($reservation->getGuest()?->getEmail() ?? '')),
            'DTSTART;VALUE=DATE:' . $reservation->getCheckinDate()->format('Ymd'),
            'DTEND;VALUE=DATE:' . $reservation->getCheckoutDate()->format('Ymd'),
            sprintf(
                'DESCRIPTION:%s',
                $this->escape(sprintf(
                    'Séjour %d nuits — %s %s — %s',
                    $nights,
                    $reservation->getTotalPrice(),
                    $reservation->getCurrency(),
                    $reservation->getGuest()?->getEmail() ?? '',
                )),
            ),
            'END:VEVENT',
        ];
    }

    private function escape(string $value): string
    {
        return str_replace(["\\", ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $value);
    }
}
