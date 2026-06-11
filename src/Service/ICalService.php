<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;

final readonly class ICalService
{
    public function __construct(
        private ReservationRepository $reservationRepository,
    ) {
    }

    /**
     * Generate iCal feed content for a property.
     */
    public function generateFeed(Property $property): string
    {
        $reservations = $this->reservationRepository->findConfirmedForProperty((string) $property->getId());

        $lines = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:-//Clone Airbnb//FR';
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:PUBLISH';
        $lines[] = 'X-WR-CALNAME:' . $this->escapeText($property->getTitle() ?? 'Logement');
        $lines[] = 'X-WR-CALDESC:Réservations confirmées';

        foreach ($reservations as $reservation) {
            $lines = array_merge($lines, $this->buildEvent($reservation));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function buildEvent(Reservation $reservation): array
    {
        $property = $reservation->getProperty();
        $guest = $reservation->getGuest();
        $propertyTitle = $property?->getTitle() ?? 'Logement';
        $guestName = $guest?->getProfile()?->getFirstName() ?? 'Voyageur';
        $guestEmail = $guest?->getEmail() ?? '';
        $checkin = $reservation->getCheckinDate();
        $checkout = $reservation->getCheckoutDate();
        $nights = 0;
        if ($checkin !== null && $checkout !== null) {
            $nights = (int) $checkin->diff($checkout)->days;
        }
        $totalPrice = $reservation->getTotalPrice() ?? '0';
        $currency = $reservation->getCurrency() ?? 'EUR';

        $uid = 'res-' . $reservation->getId() . '@clone-airbnb.local';
        $summary = $propertyTitle . ' — ' . $guestName;
        $description = sprintf('Séjour %d nuit(s) — %s %s — %s', $nights, $totalPrice, $currency, $guestEmail);

        return [
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'SUMMARY:' . $this->escapeText($summary),
            'DTSTART;VALUE=DATE:' . ($checkin?->format('Ymd') ?? ''),
            'DTEND;VALUE=DATE:' . ($checkout?->format('Ymd') ?? ''),
            'DESCRIPTION:' . $this->escapeText($description),
            'END:VEVENT',
        ];
    }

    private function escapeText(string $text): string
    {
        $text = str_replace(['\\', ';', ',', "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', ''], $text);

        return $text;
    }
}
