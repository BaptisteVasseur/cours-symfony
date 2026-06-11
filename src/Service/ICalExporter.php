<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\User;
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

        foreach ($this->reservations->findConfirmedForExport($property) as $reservation) {
            $guest = $reservation->getGuest();
            $nights = (int) $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days;
            $summary = sprintf('%s — %s', (string) $property->getTitle(), $this->guestName($guest));
            $description = sprintf(
                'Séjour %d nuit%s — %s€ — %s',
                $nights,
                $nights > 1 ? 's' : '',
                (string) $reservation->getTotalPrice(),
                $guest?->getEmail() ?? '',
            );

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:res-' . $reservation->getId() . '@clone-airbnb.local';
            $lines[] = 'SUMMARY:' . $this->escape($summary);
            $lines[] = 'DTSTART;VALUE=DATE:' . $reservation->getCheckinDate()->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $reservation->getCheckoutDate()->format('Ymd');
            $lines[] = 'DESCRIPTION:' . $this->escape($description);
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $value);
    }

    private function guestName(?User $guest): string
    {
        $profile = $guest?->getProfile();
        if ($profile !== null && ($profile->getFirstName() !== null || $profile->getLastName() !== null)) {
            return trim(($profile->getFirstName() ?? '') . ' ' . ($profile->getLastName() ?? ''));
        }

        return $guest?->getEmail() ?? 'Voyageur';
    }
}
