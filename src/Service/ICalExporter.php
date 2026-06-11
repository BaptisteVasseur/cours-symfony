<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;

/**
 * Génère le flux iCal (RFC 5545) des séjours confirmés d'un logement, destiné
 * aux agendas externes (Google Calendar, Outlook). Syntaxe stricte : lignes
 * terminées par CRLF, valeurs échappées, lignes longues pliées.
 */
final readonly class ICalExporter
{
    private const PRODID = '-//Clone Airbnb//FR';
    private const UID_DOMAIN = 'clone-airbnb.local';

    public function __construct(
        private ReservationRepository $reservationRepository,
    ) {
    }

    public function buildFeed(Property $property): string
    {
        $reservations = $this->reservationRepository->findBy(
            ['property' => $property, 'status' => 'confirmed'],
            ['checkinDate' => 'ASC'],
        );

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . self::PRODID,
            'CALSCALE:GREGORIAN',
        ];

        foreach ($reservations as $reservation) {
            $lines = [...$lines, ...$this->eventLines($property, $reservation)];
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines)) . "\r\n";
    }

    /**
     * @return list<string>
     */
    private function eventLines(Property $property, Reservation $reservation): array
    {
        $guest = $reservation->getGuest();
        $profile = $guest?->getProfile();
        $guestName = trim(sprintf('%s %s', $profile?->getFirstName() ?? '', $profile?->getLastName() ?? ''));
        if ($guestName === '') {
            $guestName = $guest?->getEmail() ?? 'Voyageur';
        }

        $nights = (int) $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days;
        $description = sprintf(
            'Séjour %d nuit%s — %s€ — %s',
            $nights,
            $nights > 1 ? 's' : '',
            rtrim(rtrim(number_format((float) $reservation->getTotalPrice(), 2, '.', ''), '0'), '.'),
            $guest?->getEmail() ?? '',
        );

        return [
            'BEGIN:VEVENT',
            sprintf('UID:res-%s@%s', $reservation->getId(), self::UID_DOMAIN),
            'DTSTAMP:' . ($reservation->getCreatedAt() ?? new \DateTimeImmutable())
                ->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z'),
            'SUMMARY:' . $this->escape($property->getTitle() . ' — ' . $guestName),
            // DTEND est exclusif : le jour de checkout n'est pas une nuit occupée
            'DTSTART;VALUE=DATE:' . $reservation->getCheckinDate()->format('Ymd'),
            'DTEND;VALUE=DATE:' . $reservation->getCheckoutDate()->format('Ymd'),
            'DESCRIPTION:' . $this->escape($description),
            'END:VEVENT',
        ];
    }

    /**
     * Échappement RFC 5545 : antislash, point-virgule, virgule, sauts de ligne.
     */
    private function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n"],
            ['\\\\', '\;', '\,', '\n', '\n'],
            $value,
        );
    }

    /**
     * Pliage des lignes longues (75 octets max par ligne) : la suite est
     * préfixée par un espace. Découpe en caractères pour ne pas casser l'UTF-8.
     */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $chunks = [];
        $current = '';
        foreach (mb_str_split($line) as $char) {
            if (strlen($current) + strlen($char) > ($chunks === [] ? 75 : 74)) {
                $chunks[] = $current;
                $current = '';
            }
            $current .= $char;
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return implode("\r\n ", $chunks);
    }
}
