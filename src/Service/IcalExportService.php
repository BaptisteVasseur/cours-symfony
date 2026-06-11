<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;

final class IcalExportService
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
    ) {
    }

    public function generate(Property $property): string
    {
        $reservations = $this->reservationRepository->findConfirmedByProperty($property);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clone Airbnb//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($reservations as $reservation) {
            $lines = array_merge($lines, $this->buildEventLines($property, $reservation));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * @return list<string>
     */
    private function buildEventLines(Property $property, Reservation $reservation): array
    {
        $guest = $reservation->getGuest();
        $profile = $guest?->getProfile();
        $guestName = trim(($profile?->getFirstName() ?? '') . ' ' . ($profile?->getLastName() ?? ''));
        if ($guestName === '') {
            $guestName = $guest?->getEmail() ?? 'Voyageur';
        }

        $checkin = $reservation->getCheckinDate();
        $checkout = $reservation->getCheckoutDate();
        $nights = $checkin !== null && $checkout !== null ? (int) $checkin->diff($checkout)->days : 0;

        $summary = $this->escape($property->getTitle() . ' — ' . $guestName);
        $description = $this->escape(sprintf(
            'Séjour %d nuits — %s€ — %s',
            $nights,
            $reservation->getTotalPrice(),
            $guest?->getEmail() ?? '',
        ));

        return [
            'BEGIN:VEVENT',
            'UID:res-' . $reservation->getId() . '@clone-airbnb.local',
            'SUMMARY:' . $summary,
            'DTSTART;VALUE=DATE:' . $checkin?->format('Ymd'),
            'DTEND;VALUE=DATE:' . $checkout?->format('Ymd'),
            'DESCRIPTION:' . $description,
            'STATUS:CONFIRMED',
            'END:VEVENT',
        ];
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $value);
    }
}
