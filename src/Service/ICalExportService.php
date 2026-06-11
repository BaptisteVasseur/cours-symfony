<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\ReservationRepository;

final class ICalExportService
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
    ) {}

    public function generate(Property $property): string
    {
        $reservations = $this->reservationRepository->findConfirmedForProperty($property);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clone Airbnb//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($reservations as $reservation) {
            $guest = $reservation->getGuest();
            $guestEmail = $guest?->getEmail() ?? 'inconnu';
            $checkin = $reservation->getCheckinDate()->format('Ymd');
            $checkout = $reservation->getCheckoutDate()->format('Ymd');
            $nights = (int) $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days;
            $total = $reservation->getTotalPrice();
            $uid = 'res-' . $reservation->getId() . '@clone-airbnb.local';
            $summary = $this->escape($property->getTitle()) . ' — ' . $this->escape($guestEmail);
            $description = sprintf('Séjour %d nuit(s) — %s€ — %s', $nights, $total, $guestEmail);

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid;
            $lines[] = 'SUMMARY:' . $summary;
            $lines[] = 'DTSTART;VALUE=DATE:' . $checkin;
            $lines[] = 'DTEND;VALUE=DATE:' . $checkout;
            $lines[] = 'DESCRIPTION:' . $this->escape($description);
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', ',', ';', "\n"], ['\\\\', '\\,', '\\;', '\\n'], $value);
    }
}
