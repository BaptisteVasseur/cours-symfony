<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\ReservationRepository;

final class ICalExportService
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
    ) {}

    public function generate(Property $property): string
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $dtstamp = $now->format('Ymd\THis\Z');
        $prodId = '-//StayHub//Reservation Calendar//FR';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:'.$prodId,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.self::escape($property->getTitle() ?? 'Calendrier'),
            'X-WR-TIMEZONE:Europe/Paris',
        ];

        $start = new \DateTimeImmutable('-1 year');
        $end   = new \DateTimeImmutable('+2 years');
        $reservations = $this->reservationRepository->findConfirmedForPeriod($property, $start, $end);

        foreach ($reservations as $reservation) {
            $uid     = $reservation->getId().'@stayhub.local';
            $dtstart = $reservation->getCheckinDate()?->format('Ymd') ?? '';
            $dtend   = $reservation->getCheckoutDate()?->format('Ymd') ?? '';

            $guestProfile = $reservation->getGuest()?->getProfile();
            $guestName = $guestProfile
                ? trim(($guestProfile->getFirstName() ?? '').' '.($guestProfile->getLastName() ?? ''))
                : ($reservation->getGuest()?->getEmail() ?? 'Voyageur');

            $guestEmail = $reservation->getGuest()?->getEmail() ?? '';

            $nights = 0;
            if ($reservation->getCheckinDate() && $reservation->getCheckoutDate()) {
                $nights = (int) $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days;
            }

            $montant = number_format((float) ($reservation->getTotalPrice() ?? 0), 0, ',', ' ');

            $summary     = 'Réservation — '.self::escape($guestName);
            $description = self::escape(
                sprintf('Séjour %d nuit%s — %s€ — %s', $nights, $nights > 1 ? 's' : '', $montant, $guestEmail)
            );

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$uid;
            $lines[] = 'DTSTAMP:'.$dtstamp;
            $lines[] = 'DTSTART;VALUE=DATE:'.$dtstart;
            $lines[] = 'DTEND;VALUE=DATE:'.$dtend;
            $lines[] = 'SUMMARY:'.$summary;
            $lines[] = 'DESCRIPTION:'.$description;
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    private static function escape(string $text): string
    {
        $text = str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $text);

        return $text;
    }
}
