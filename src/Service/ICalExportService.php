<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;

/**
 * Builds an iCal (.ics) string from a list of confirmed reservations.
 *
 * Follows RFC 5545 conventions:
 *   - DTEND is exclusive (checkout day is not part of the stay)
 *   - Line endings are CRLF (\r\n)
 *   - Dates use the YYYYMMDD format for VALUE=DATE
 */
final class ICalExportService
{
    private const PRODID = '-//Clone Airbnb//FR';
    private const CALENDAR_NAME = 'Clone Airbnb — Réservations';

    /**
     * Generates the full .ics calendar content for a property's confirmed stays.
     *
     * @param Reservation[] $reservations
     */
    public function generate(Property $property, array $reservations): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . self::PRODID,
            'CALSCALE:GREGORIAN',
            'X-WR-CALNAME:' . self::escape($property->getTitle() ?? self::CALENDAR_NAME),
        ];

        foreach ($reservations as $reservation) {
            $guest = $reservation->getGuest();
            $guestName = $guest?->getProfile()?->getFirstName() . ' ' . $guest?->getProfile()?->getLastName();
            $guestName = trim($guestName) ?: ($guest?->getEmail() ?? 'Inconnu');
            $guestEmail = $guest?->getEmail() ?? '';
            $nights = (int) $reservation->getCheckinDate()?->diff($reservation->getCheckoutDate())->days;

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:res-' . $reservation->getId() . '@clone-airbnb.local';
            $lines[] = 'SUMMARY:' . self::escape($property->getTitle() . ' — ' . $guestName);
            $lines[] = 'DTSTART;VALUE=DATE:' . $reservation->getCheckinDate()?->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $reservation->getCheckoutDate()?->format('Ymd');
            $lines[] = 'DESCRIPTION:' . self::escape(sprintf(
                'Séjour %d nuit(s) — %s€ — %s',
                $nights,
                $reservation->getTotalPrice(),
                $guestEmail,
            ));
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Escapes special characters for iCal text values.
     * Replaces \, ;, \n, and preserves line folding.
     */
    private static function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n"],
            ['\\\\', '\\;', '\\,', "\\n", "\\n"],
            $value,
        );
    }
}
