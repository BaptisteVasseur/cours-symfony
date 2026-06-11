<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;

/**
 * Builds an RFC 5545 (.ics) feed listing the confirmed stays of a property.
 * DTEND is exclusive (the checkout day), matching the half-open booking model.
 */
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
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:res-' . $reservation->getId() . '@clone-airbnb.local';
            $lines[] = 'SUMMARY:' . $this->escape($property->getTitle() . ' — ' . $this->guestName($reservation));
            $lines[] = 'DTSTART;VALUE=DATE:' . $reservation->getCheckinDate()->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $reservation->getCheckoutDate()->format('Ymd');
            $lines[] = 'DESCRIPTION:' . $this->escape($this->description($reservation));
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    private function description(Reservation $reservation): string
    {
        $nights = (int) $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days;

        return sprintf(
            'Séjour %d nuit%s — %s %s — %s',
            $nights,
            $nights > 1 ? 's' : '',
            $reservation->getTotalPrice(),
            $reservation->getCurrency(),
            $reservation->getGuest()?->getEmail() ?? '',
        );
    }

    private function guestName(Reservation $reservation): string
    {
        $profile = $reservation->getGuest()?->getProfile();
        if ($profile !== null && ($profile->getFirstName() !== null || $profile->getLastName() !== null)) {
            return trim($profile->getFirstName() . ' ' . $profile->getLastName());
        }

        return $reservation->getGuest()?->getEmail() ?? 'Voyageur';
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n"],
            ['\\\\', '\;', '\,', '\n', '\n'],
            $value,
        );
    }
}
