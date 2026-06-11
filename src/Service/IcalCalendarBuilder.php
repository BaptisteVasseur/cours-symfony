<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;

/**
 * Construit le flux iCalendar (.ics) d'un logement à partir de ses séjours confirmés.
 * Conforme à la RFC 5545 : lignes terminées par CRLF, valeurs texte échappées.
 */
final class IcalCalendarBuilder
{
    private const string PRODID = '-//Clone Airbnb//FR';
    private const string UID_DOMAIN = 'clone-airbnb.local';

    /**
     * @param list<Reservation> $reservations séjours confirmés du logement
     */
    public function build(Property $property, array $reservations): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . self::PRODID,
            'CALSCALE:GREGORIAN',
        ];

        foreach ($reservations as $reservation) {
            $checkin = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();
            if ($checkin === null || $checkout === null) {
                continue;
            }

            $nights = max(1, (int) $checkin->diff($checkout)->days);
            $guest = $reservation->getGuest();
            $guestName = $this->guestName($reservation);
            $guestEmail = $guest?->getEmail() ?? '';
            $total = rtrim(rtrim((string) $reservation->getTotalPrice(), '0'), '.');

            $summary = sprintf('%s — %s', (string) $property->getTitle(), $guestName);
            $description = sprintf(
                'Séjour %d nuit%s — %s%s — %s',
                $nights,
                $nights > 1 ? 's' : '',
                $total,
                $reservation->getCurrency() === 'EUR' ? '€' : (' ' . $reservation->getCurrency()),
                $guestEmail,
            );

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:res-' . $reservation->getId() . '@' . self::UID_DOMAIN;
            $lines[] = 'DTSTAMP:' . ($reservation->getCreatedAt() ?? $checkin)->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:' . $this->escape($summary);
            $lines[] = 'DTSTART;VALUE=DATE:' . $checkin->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $checkout->format('Ymd');
            $lines[] = 'DESCRIPTION:' . $this->escape($description);
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        // RFC 5545 : séparateur de lignes CRLF, avec CRLF final.
        return implode("\r\n", $lines) . "\r\n";
    }

    private function guestName(Reservation $reservation): string
    {
        $profile = $reservation->getGuest()?->getProfile();
        if ($profile !== null && ($profile->getFirstName() || $profile->getLastName())) {
            return trim(($profile->getFirstName() ?? '') . ' ' . ($profile->getLastName() ?? ''));
        }

        return $reservation->getGuest()?->getEmail() ?? 'Voyageur';
    }

    /**
     * Échappement des caractères spéciaux dans une valeur TEXT iCalendar.
     */
    private function escape(string $value): string
    {
        $value = str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $value);
        $value = str_replace(["\r\n", "\n", "\r"], '\\n', $value);

        return $value;
    }
}
