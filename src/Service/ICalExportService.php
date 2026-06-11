<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\User;
use App\Repository\ReservationRepository;

final class ICalExportService
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
    ) {}

    public function generateForProperty(Property $property): string
    {
        $reservations = $this->reservationRepository->findConfirmedByProperty($property);

        $lines = $this->calendarHeader('Réservations — ' . ($property->getTitle() ?? 'Logement'));

        foreach ($reservations as $reservation) {
            $guest = $reservation->getGuest();
            $name  = $guest->getProfile()?->getFirstName() . ' ' . $guest->getProfile()?->getLastName();
            $lines .= $this->buildVEvent(
                uid:         (string) $reservation->getId() . '@airbnb-clone',
                dtStart:     $reservation->getCheckinDate(),
                dtEnd:       $reservation->getCheckoutDate(),
                summary:     'Réservé — ' . trim($name),
                description: sprintf('%d voyageur(s)', $reservation->getGuestsCount() ?? 1),
                created:     $reservation->getCreatedAt(),
            );
        }

        $lines .= "END:VCALENDAR\r\n";

        return $lines;
    }

    public function generateForHost(User $host): string
    {
        $lines = $this->calendarHeader('Toutes mes réservations');

        foreach ($host->getProperties() as $property) {
            $reservations = $this->reservationRepository->findConfirmedByProperty($property);
            foreach ($reservations as $reservation) {
                $guest = $reservation->getGuest();
                $name  = $guest->getProfile()?->getFirstName() . ' ' . $guest->getProfile()?->getLastName();
                $lines .= $this->buildVEvent(
                    uid:         (string) $reservation->getId() . '@airbnb-clone',
                    dtStart:     $reservation->getCheckinDate(),
                    dtEnd:       $reservation->getCheckoutDate(),
                    summary:     ($property->getTitle() ?? 'Logement') . ' — ' . trim($name),
                    description: sprintf('%d voyageur(s)', $reservation->getGuestsCount() ?? 1),
                    created:     $reservation->getCreatedAt(),
                );
            }
        }

        $lines .= "END:VCALENDAR\r\n";

        return $lines;
    }

    private function calendarHeader(string $calName): string
    {
        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//AirbnbClone//BookingEngine//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeText($calName),
            'X-WR-TIMEZONE:Europe/Paris',
        ]) . "\r\n";
    }

    private function buildVEvent(
        string $uid,
        ?\DateTimeImmutable $dtStart,
        ?\DateTimeImmutable $dtEnd,
        string $summary,
        string $description,
        ?\DateTimeImmutable $created,
    ): string {
        $dtStartStr = $dtStart?->format('Ymd') ?? date('Ymd');
        $dtEndStr   = $dtEnd?->format('Ymd')   ?? date('Ymd');
        $createdStr = ($created ?? new \DateTimeImmutable())->format('Ymd\THis\Z');
        $now        = (new \DateTimeImmutable())->format('Ymd\THis\Z');

        return implode("\r\n", [
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $now,
            'DTSTART;VALUE=DATE:' . $dtStartStr,
            'DTEND;VALUE=DATE:' . $dtEndStr,
            'SUMMARY:' . $this->escapeText($summary),
            'DESCRIPTION:' . $this->escapeText($description),
            'CREATED:' . $createdStr,
            'STATUS:CONFIRMED',
            'TRANSP:OPAQUE',
            'END:VEVENT',
        ]) . "\r\n";
    }

    private function escapeText(string $text): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $text);
    }
}
