<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;

/**
 * Génère un flux iCal (RFC 5545) des séjours confirmés d'un logement (Partie E).
 */
final class ICalExporter
{
    /**
     * @param iterable<Reservation> $reservations
     */
    public function export(Property $property, iterable $reservations): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clone Airbnb//FR',
            'CALSCALE:GREGORIAN',
        ];

        $stamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Ymd\THis\Z');

        foreach ($reservations as $reservation) {
            $checkin = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();
            $nights = (int) $checkin->diff($checkout)->days;
            $guest = $reservation->getGuest();

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:res-' . $reservation->getId() . '@clone-airbnb.local';
            $lines[] = 'DTSTAMP:' . $stamp;
            $lines[] = 'SUMMARY:' . $this->escape($property->getTitle() . ' — ' . $this->guestName($guest));
            $lines[] = 'DTSTART;VALUE=DATE:' . $checkin->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $checkout->format('Ymd');
            $lines[] = 'DESCRIPTION:' . $this->escape(sprintf(
                'Séjour %d nuits — %s€ — %s',
                $nights,
                $this->amount($reservation->getTotalPrice()),
                $guest?->getEmail() ?? '',
            ));
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    private function guestName(?User $guest): string
    {
        $profile = $guest?->getProfile();
        if ($profile !== null && ($profile->getFirstName() !== null || $profile->getLastName() !== null)) {
            return trim(($profile->getFirstName() ?? '') . ' ' . ($profile->getLastName() ?? ''));
        }

        return $guest?->getEmail() ?? 'Voyageur';
    }

    private function amount(?string $total): string
    {
        if ($total === null) {
            return '0';
        }

        return rtrim(rtrim(number_format((float) $total, 2, '.', ''), '0'), '.');
    }

    /**
     * Échappement des caractères réservés iCal (RFC 5545 §3.3.11).
     */
    private function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], $value);
    }
}
