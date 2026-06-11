<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\Property;
use App\Repository\ReservationRepository;

final class PropertyICalExporter
{
    public function __construct(
        private ReservationRepository $reservationRepository,
    ) {
    }

    public function export(Property $property): string
    {
        $title = $this->shortenText($property->getTitle() ?? 'Logement', 40);
        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Airbnb Clone//iCal Export//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeText($title),
            'X-WR-CALDESC:' . $this->escapeText('Reservations confirmees'),
        ];

        foreach ($this->reservationRepository->findUpcomingConfirmedForProperty($property, new \DateTimeImmutable('today')) as $reservation) {
            $checkin = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();

            if ($checkin === null || $checkout === null || $reservation->getId() === null) {
                continue;
            }

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $reservation->getId() . '@airbnb-clone.local';
            $lines[] = 'DTSTAMP:' . $timestamp;
            $lines[] = 'DTSTART;VALUE=DATE:' . $checkin->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $checkout->format('Ymd');
            $lines[] = 'SUMMARY:' . $this->escapeText('Reservation confirmee');
            $lines[] = 'DESCRIPTION:' . $this->escapeText('Logement: ' . $title);
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    private function escapeText(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\;', '\,', '\n', '\n', '\n'],
            $value,
        );
    }

    private function shortenText(string $value, int $maxLength): string
    {
        $shortened = iconv_substr($value, 0, $maxLength, 'UTF-8');

        return $shortened !== false ? $shortened : $value;
    }
}
