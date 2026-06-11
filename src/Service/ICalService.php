<?php

namespace App\Service;

use App\Entity\Property;
use App\Repository\BookingRepository;

class ICalService
{
    public function __construct(
        private readonly BookingRepository $bookingRepo,
    ) {}

    public function generateCalendar(Property $property): string
    {
        $bookings = $this->bookingRepo->findConfirmedForProperty($property);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clone Airbnb//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeIcal($property->getTitle()),
            'X-WR-TIMEZONE:Europe/Paris',
        ];

        foreach ($bookings as $booking) {
            $uid = 'res-' . $booking->getId() . '@clone-airbnb.local';
            $summary = $this->escapeIcal($property->getTitle())
                . ' — '
                . $this->escapeIcal($booking->getTraveler()->getFirstName() . ' ' . $booking->getTraveler()->getLastName());

            $nights = $booking->getNights();
            $description = sprintf(
                'Séjour %d nuit(s) — %s€ — %s',
                $nights,
                $booking->getTotalPrice(),
                $booking->getTraveler()->getEmail()
            );

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid;
            $lines[] = 'DTSTAMP:' . (new \DateTimeImmutable())->format('Ymd\THis\Z');
            $lines[] = 'DTSTART;VALUE=DATE:' . $booking->getCheckIn()->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $booking->getCheckOut()->format('Ymd');
            $lines[] = 'SUMMARY:' . $summary;
            $lines[] = 'DESCRIPTION:' . $this->escapeIcal($description);
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    private function escapeIcal(string $text): string
    {
        return str_replace(["\n", "\r", ',', ';', '\\'], ['\\n', '', '\\,', '\\;', '\\\\'], $text);
    }
}
