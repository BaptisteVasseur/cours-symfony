<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\ReservationRepository;

/**
 * Génère un flux iCal (.ics) des séjours confirmés d'un logement (Partie E).
 * Format conforme à la RFC 5545 : lignes en CRLF, valeurs échappées.
 */
final readonly class IcalExportService
{
    private const PRODID = '-//Airstay//Clone Airbnb//FR';
    private const DOMAIN = 'airstay.local';

    public function __construct(
        private ReservationRepository $reservationRepository,
    ) {
    }

    public function export(Property $property): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . self::PRODID,
            'CALSCALE:GREGORIAN',
            'X-WR-CALNAME:' . $this->escape($property->getTitle() ?? 'Calendrier'),
        ];

        foreach ($this->reservationRepository->findConfirmedForCalendar($property) as $reservation) {
            $checkin = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();
            $nights = (int) $checkin->diff($checkout)->days;
            $guestEmail = $reservation->getGuest()?->getEmail() ?? '';

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:res-' . $reservation->getId() . '@' . self::DOMAIN;
            $lines[] = 'DTSTAMP:' . (new \DateTimeImmutable())->format('Ymd\THis\Z');
            $lines[] = 'DTSTART;VALUE=DATE:' . $checkin->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $checkout->format('Ymd');
            $lines[] = 'SUMMARY:' . $this->escape(($property->getTitle() ?? '') . ' — ' . $guestEmail);
            $lines[] = 'DESCRIPTION:' . $this->escape(sprintf(
                'Séjour %d nuit(s) — %s %s — %s',
                $nights,
                $reservation->getTotalPrice() ?? '0',
                $reservation->getCurrency() ?? '',
                $guestEmail,
            ));
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\n"],
            ['\\\\', '\\;', '\\,', '\\n'],
            $value,
        );
    }
}
