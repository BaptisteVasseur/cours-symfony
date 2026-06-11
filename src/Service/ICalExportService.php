<?php

namespace App\Service;

use App\Entity\Listing;
use App\Repository\BlockedPeriodRepository;
use App\Repository\BookingRepository;

class ICalExportService
{
    public function __construct(
        private readonly BookingRepository       $bookingRepo,
        private readonly BlockedPeriodRepository $blockedPeriodRepo,
    ) {}

    public function generate(Listing $listing): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Airbnb Clone//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escape($listing->getTitle()),
            'X-WR-TIMEZONE:Europe/Paris',
        ];

        foreach ($this->bookingRepo->findBy(['listing' => $listing, 'status' => 'confirmed']) as $booking) {
            $guest        = $booking->getGuest();
            $guestName    = $guest->getProfile()
                ? $guest->getProfile()->getFirstName() . ' ' . $guest->getProfile()->getLastName()
                : $guest->getEmail();
            $nights       = (int) $booking->getStartDate()->diff($booking->getEndDate())->days;
            $description  = sprintf(
                'Séjour %d nuit%s — %s€ — %s',
                $nights,
                $nights > 1 ? 's' : '',
                $booking->getTotalPrice(),
                $guest->getEmail(),
            );

            $lines = array_merge($lines, [
                'BEGIN:VEVENT',
                'UID:res-' . $booking->getId() . '@airbnb-clone.local',
                'DTSTART;VALUE=DATE:' . $booking->getStartDate()->format('Ymd'),
                'DTEND;VALUE=DATE:' . $booking->getEndDate()->format('Ymd'),
                'SUMMARY:' . $this->escape($listing->getTitle() . ' — ' . $guestName),
                'DESCRIPTION:' . $this->escape($description),
                'STATUS:CONFIRMED',
                'END:VEVENT',
            ]);
        }

        foreach ($this->blockedPeriodRepo->findBy(['listing' => $listing]) as $bp) {
            $lines = array_merge($lines, [
                'BEGIN:VEVENT',
                'UID:blocked-' . $bp->getId() . '@airbnb-clone.local',
                'DTSTART;VALUE=DATE:' . $bp->getStartDate()->format('Ymd'),
                'DTEND;VALUE=DATE:' . $bp->getEndDate()->format('Ymd'),
                'SUMMARY:Indisponible' . ($bp->getReason() ? ' — ' . $bp->getReason() : ''),
                'STATUS:CONFIRMED',
                'END:VEVENT',
            ]);
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $value);
    }
}
