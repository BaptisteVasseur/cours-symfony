<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Listing;
use App\Repository\BookingRepository;

final class ICalExportService
{
    private const PRODID = '-//Clone Airbnb//FR';
    private const DOMAIN = 'clone-airbnb.local';
    private const EOL = "\r\n";

    public function __construct(private readonly BookingRepository $bookingRepository)
    {
    }

    public function export(Listing $listing): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . self::PRODID,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escape($listing->getTitle() ?? 'Calendrier'),
        ];

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Ymd\THis\Z');

        foreach ($this->bookingRepository->findConfirmedForListing($listing) as $booking) {
            array_push($lines, ...$this->buildEvent($booking, $now));
        }

        $lines[] = 'END:VCALENDAR';

        return implode(self::EOL, array_map([$this, 'fold'], $lines)) . self::EOL;
    }

    private function buildEvent(Booking $booking, string $dtstamp): array
    {
        $guest = $booking->getGuest();
        $guestName = trim(($guest?->getFirstName() ?? '') . ' ' . ($guest?->getLastName() ?? ''));
        $summary = sprintf('%s — %s', $booking->getListing()->getTitle(), $guestName !== '' ? $guestName : 'Voyageur');

        $description = sprintf(
            'Séjour %d nuit(s) — %s%s — %s',
            $booking->getNightsCount() ?? 0,
            $booking->getTotalAmount() ?? '0',
            $booking->getCurrency() ?? 'EUR',
            $guest?->getEmail() ?? '',
        );

        return [
            'BEGIN:VEVENT',
            'UID:res-' . $booking->getId() . '@' . self::DOMAIN,
            'DTSTAMP:' . $dtstamp,
            'DTSTART;VALUE=DATE:' . $booking->getCheckIn()->format('Ymd'),
            'DTEND;VALUE=DATE:' . $booking->getCheckOut()->format('Ymd'),
            'SUMMARY:' . $this->escape($summary),
            'DESCRIPTION:' . $this->escape($description),
            'STATUS:CONFIRMED',
            'TRANSP:OPAQUE',
            'END:VEVENT',
        ];
    }
    private function escape(string $value): string
    {
        return str_replace(
            ['\\', ',', ';', "\r\n", "\n", "\r"],
            ['\\\\', '\\,', '\\;', '\\n', '\\n', '\\n'],
            $value,
        );
    }

    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = '';
        $current = '';
        foreach (str_split($line) as $char) {
            if (strlen($current . $char) > 75) {
                $folded .= ($folded === '' ? '' : self::EOL . ' ') . $current;
                $current = '';
            }
            $current .= $char;
        }
        $folded .= ($folded === '' ? '' : self::EOL . ' ') . $current;

        return $folded;
    }
}
