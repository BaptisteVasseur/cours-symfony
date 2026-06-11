<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\ReservationRepository;
use Symfony\Component\Uid\Uuid;

final class ICalService
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
    ) {}

    /**
     * Generates a VCALENDAR string (RFC 5545) for all confirmed reservations of a property.
     */
    public function generateIcs(Property $property): string
    {
        $reservations = $this->reservationRepository->findConfirmedOverlapping(
            $property,
            new \DateTimeImmutable('2000-01-01'),
            new \DateTimeImmutable('2100-01-01'),
        );

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//AirbnbClone//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($reservations as $reservation) {
            $uid = ((string) $reservation->getId()) . '@airbnb-clone.local';
            $dtstart = $reservation->getCheckinDate()->format('Ymd');
            $dtend = $reservation->getCheckoutDate()->format('Ymd');
            $summary = $this->escapeIcal(
                ($property->getTitle() ?? 'Réservation') . ' — ' .
                ($reservation->getGuest()?->getEmail() ?? ''),
            );
            $description = $this->escapeIcal(sprintf(
                'Séjour %d nuits — %s€ — %s',
                (int) $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days,
                $reservation->getTotalPrice(),
                $reservation->getGuest()?->getEmail() ?? '',
            ));
            $dtstamp = (new \DateTimeImmutable())->format('Ymd\THis\Z');

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid;
            $lines[] = 'DTSTAMP:' . $dtstamp;
            $lines[] = 'DTSTART;VALUE=DATE:' . $dtstart;
            $lines[] = 'DTEND;VALUE=DATE:' . $dtend;
            $lines[] = 'SUMMARY:' . $summary;
            $lines[] = 'DESCRIPTION:' . $description;
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Generates a new UUID token for a property and sets it.
     * Does NOT flush — caller must call em->flush().
     */
    public function regenerateToken(Property $property): Uuid
    {
        $token = Uuid::v4();
        $property->setIcalToken($token);

        return $token;
    }

    private function escapeIcal(string $value): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $value);
    }
}
