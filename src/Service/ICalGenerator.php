<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;

class ICalGenerator
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
    ) {}

    public function generate(Property $property): string
    {
        $reservations = $this->reservationRepository->createQueryBuilder('r')
            ->where('r.property = :property')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('property', $property)
            ->setParameter('statuses', ['confirmed', 'pending'])
            ->orderBy('r.checkinDate', 'ASC')
            ->getQuery()
            ->getResult();

        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//Plateforme Réservation//FR\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:PUBLISH\r\n";

        foreach ($reservations as $reservation) {
            $ical .= $this->generateEvent($reservation);
        }

        $ical .= "END:VCALENDAR\r\n";

        return $ical;
    }

    private function generateEvent(Reservation $reservation): string
    {
        $uid = $reservation->getId() . '@' . ($_ENV['DEFAULT_URI'] ?? 'localhost');
        $property = $reservation->getProperty();
        $guest = $reservation->getGuest();
        
        $guestName = $guest?->getProfile()?->getFirstName() 
            ?? $guest?->getEmail() 
            ?? 'Voyageur';
        
        $summary = sprintf(
            '%s — %s',
            $property?->getTitle() ?? 'Logement',
            $guestName
        );
        
        $start = $reservation->getCheckinDate()->format('Ymd');
        $end = $reservation->getCheckoutDate()->format('Ymd');
        
        $description = sprintf(
            "Séjour de %d nuits\nVoyageurs : %d\nStatut : %s\nTotal : %s €",
            (int) $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days,
            $reservation->getGuestsCount(),
            $reservation->getStatus(),
            number_format((float) $reservation->getTotalPrice(), 2)
        );

        return "BEGIN:VEVENT\r\n"
            . "UID:$uid\r\n"
            . "DTSTAMP:" . (new \DateTimeImmutable())->format('Ymd\THis\Z') . "\r\n"
            . "DTSTART;VALUE=DATE:$start\r\n"
            . "DTEND;VALUE=DATE:$end\r\n"
            . "SUMMARY:" . str_replace([',', ';', "\n"], ' ', $summary) . "\r\n"
            . "DESCRIPTION:" . str_replace([',', ';', "\n"], ' ', $description) . "\r\n"
            . "END:VEVENT\r\n";
    }
}