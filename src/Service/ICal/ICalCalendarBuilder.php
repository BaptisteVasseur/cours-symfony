<?php

declare(strict_types=1);

namespace App\Service\ICal;

use App\Entity\Property;
use App\Entity\Reservation;

final readonly class ICalCalendarBuilder
{
    /**
     * @param list<Reservation> $reservations
     */
    public function build(Property $property, array $reservations): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Cours Symfony Airbnb//Reservations//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        foreach ($reservations as $reservation) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:reservation-'.$reservation->getId().'@cours-symfony-airbnb';
            $lines[] = 'DTSTAMP:'.$now->format('Ymd\THis\Z');
            $lines[] = 'DTSTART;VALUE=DATE:'.$this->formatDate($reservation->getCheckinDate());
            $lines[] = 'DTEND;VALUE=DATE:'.$this->formatDate($reservation->getCheckoutDate());
            $lines[] = 'SUMMARY:'.$this->escapeText($property->getTitle() ?? 'Reservation');
            $lines[] = 'DESCRIPTION:'.$this->escapeText('Réservation confirmée');
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->foldLine(...), $lines))."\r\n";
    }

    private function formatDate(?\DateTimeImmutable $date): string
    {
        if ($date === null) {
            throw new \LogicException('Une réservation exportée doit avoir des dates.');
        }

        return $date->format('Ymd');
    }

    private function escapeText(string $value): string
    {
        return str_replace(
            ["\\", "\r\n", "\n", ';', ','],
            ['\\\\', '\\n', '\\n', '\\;', '\\,'],
            $value,
        );
    }

    private function foldLine(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $chunks = str_split($line, 74);

        return array_shift($chunks)."\r\n ".implode("\r\n ", $chunks);
    }
}
