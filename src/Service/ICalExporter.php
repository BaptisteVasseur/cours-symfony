<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\ReservationRepository;

class ICalExporter
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
    ) {
    }

    public function export(Property $property): string
    {
        $lines = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:-//Clone Airbnb//FR';
        $lines[] = 'CALSCALE:GREGORIAN';

        foreach ($this->reservationRepository->findConfirmedForProperty($property) as $reservation) {
            $guest = $reservation->getGuest();
            $profile = $guest?->getProfile();
            $name = $profile
                ? trim($profile->getFirstName() . ' ' . $profile->getLastName())
                : ($guest?->getEmail() ?? 'Voyageur');
            $nights = $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days;

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:res-' . $reservation->getId() . '@clone-airbnb.local';
            $lines[] = 'DTSTAMP:' . (new \DateTimeImmutable())->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:' . $this->escape($property->getTitle() . ' — ' . $name);
            $lines[] = 'DTSTART;VALUE=DATE:' . $reservation->getCheckinDate()->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $reservation->getCheckoutDate()->format('Ymd');
            $lines[] = 'DESCRIPTION:' . $this->escape(sprintf(
                'Séjour %d nuits — %s€ — %s',
                $nights,
                $reservation->getTotalPrice(),
                $guest?->getEmail() ?? ''
            ));
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $text);
    }
}