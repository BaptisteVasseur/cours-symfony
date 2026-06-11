<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;

final class ICalExportService
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
    ) {
    }

    public function generateIcs(Property $property): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clone Airbnb//FR',
            'CALSCALE:GREGORIAN',
        ];

        $dtstamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Ymd\THis\Z');

        foreach ($this->reservationRepository->findConfirmedForIcal($property) as $reservation) {
            $checkin = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();
            $guest = $reservation->getGuest();

            if ($checkin === null || $checkout === null || !$guest instanceof User) {
                continue;
            }

            $nights = (int) $checkin->diff($checkout)->days;
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = sprintf('UID:res-%s@clone-airbnb.local', $reservation->getId());
            $lines[] = sprintf('SUMMARY:%s', $this->escapeText(sprintf('%s — %s', $property->getTitle() ?? 'Logement', $this->guestName($guest))));
            $lines[] = sprintf('DTSTART;VALUE=DATE:%s', $checkin->format('Ymd'));
            $lines[] = sprintf('DTEND;VALUE=DATE:%s', $checkout->format('Ymd'));
            $lines[] = sprintf(
                'DESCRIPTION:%s',
                $this->escapeText(sprintf('Séjour %d nuits — %s€ — %s', $nights, $reservation->getTotalPrice() ?? '0.00', $guest->getEmail() ?? ''))
            );
            $lines[] = sprintf('DTSTAMP:%s', $dtstamp);
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(fn (string $line): string => $this->foldLine($line), $lines))."\r\n";
    }

    private function guestName(User $guest): string
    {
        $profile = $guest->getProfile();
        $name = trim(sprintf('%s %s', $profile?->getFirstName() ?? '', $profile?->getLastName() ?? ''));

        return $name !== '' ? $name : (string) $guest->getEmail();
    }

    private function escapeText(string $value): string
    {
        return strtr($value, [
            '\\' => '\\\\',
            ';' => '\;',
            ',' => '\,',
            "\r\n" => '\n',
            "\n" => '\n',
            "\r" => '\n',
        ]);
    }

    private function foldLine(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = [];
        $current = '';
        $limit = 75;
        $chars = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($chars as $char) {
            if ($current !== '' && strlen($current.$char) > $limit) {
                $folded[] = $current;
                $current = '';
                $limit = 74;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $folded[] = $current;
        }

        return implode("\r\n ", $folded);
    }
}
