<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;

final readonly class IcalService
{
    public function __construct(private ReservationRepository $reservationRepository)
    {
    }

    public function buildCalendar(Property $property): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clone Airbnb//FR',
        ];

        foreach ($this->reservationRepository->findConfirmedForICal($property) as $reservation) {
            $lines = [...$lines, ...$this->buildEvent($property, $reservation)];
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * @return list<string>
     */
    private function buildEvent(Property $property, Reservation $reservation): array
    {
        $guest = $reservation->getGuest();
        $profile = $guest?->getProfile();
        $guestName = trim((string) $profile?->getFirstName() . ' ' . (string) $profile?->getLastName());
        if ($guestName === '') {
            $guestName = $guest?->getEmail() ?? 'Voyageur';
        }

        $checkin = $reservation->getCheckinDate();
        $checkout = $reservation->getCheckoutDate();
        $nights = $checkin !== null && $checkout !== null ? (int) $checkin->diff($checkout)->days : 0;
        $id = $reservation->getId()?->toRfc4122() ?? spl_object_hash($reservation);

        return [
            'BEGIN:VEVENT',
            'UID:res-' . $id . '@clone-airbnb.local',
            'SUMMARY:' . $this->escapeText((string) $property->getTitle() . ' - ' . $guestName),
            'DTSTART;VALUE=DATE:' . $this->formatDate($checkin),
            'DTEND;VALUE=DATE:' . $this->formatDate($checkout),
            'DESCRIPTION:' . $this->escapeText(sprintf(
                'Sejour %d nuits - %s %s - %s',
                $nights,
                $reservation->getTotalPrice(),
                $reservation->getCurrency() ?? 'EUR',
                $guest?->getEmail() ?? '',
            )),
            'END:VEVENT',
        ];
    }

    private function formatDate(?\DateTimeImmutable $date): string
    {
        return ($date ?? new \DateTimeImmutable())->format('Ymd');
    }

    private function escapeText(string $value): string
    {
        return str_replace(
            ["\\", "\r", "\n", ';', ','],
            ['\\\\', '', '\\n', '\\;', '\\,'],
            $value,
        );
    }
}
