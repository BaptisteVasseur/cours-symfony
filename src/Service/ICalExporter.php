<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;

final class ICalExporter
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
            'CALSCALE:GREGORIAN',
        ];

        foreach ($this->reservations->findConfirmedForProperty($property) as $reservation) {
            foreach ($this->event($property, $reservation) as $line) {
                $lines[] = $line;
            }
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * @return list<string>
     */
    private function event(Property $property, Reservation $reservation): array
    {
        $guest = $reservation->getGuest();
        $profile = $guest?->getProfile();
        $guestName = $profile !== null
            ? trim($profile->getFirstName().' '.$profile->getLastName())
            : ($guest?->getEmail() ?? 'Voyageur');

        $nights = (int) $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days;
        $description = sprintf(
            'Séjour %d nuits — %s€ — %s',
            $nights,
            $reservation->getTotalPrice(),
            $guest?->getEmail() ?? '',
        );

        return [
            'BEGIN:VEVENT',
            'UID:res-'.$reservation->getId().'@clone-airbnb.local',
            'SUMMARY:'.$this->escape($property->getTitle().' — '.$guestName),
            'DTSTART;VALUE=DATE:'.$reservation->getCheckinDate()->format('Ymd'),
            'DTEND;VALUE=DATE:'.$reservation->getCheckoutDate()->format('Ymd'),
            'DESCRIPTION:'.$this->escape($description),
            'END:VEVENT',
        ];
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', "\n", ',', ';'],
            ['\\\\', '\\n', '\\,', '\\;'],
            $value,
        );
    }
}
