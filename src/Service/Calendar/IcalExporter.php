<?php

declare(strict_types=1);

namespace App\Service\Calendar;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;

final class IcalExporter
{
    private const UID_DOMAIN = 'clone-airbnb.local';

    /**
     * @param list<Reservation> $reservations
     */
    public function build(Property $property, array $reservations): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clone Airbnb//FR',
        ];

        foreach ($reservations as $reservation) {
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
        $checkin = $reservation->getCheckinDate();
        $checkout = $reservation->getCheckoutDate();
        $guest = $reservation->getGuest();

        $nights = $checkin->diff($checkout)->days;
        $description = sprintf(
            'Séjour %d nuit%s — %s — %s',
            $nights,
            $nights > 1 ? 's' : '',
            $this->formatPrice($reservation),
            $guest?->getEmail() ?? '',
        );

        return [
            'BEGIN:VEVENT',
            sprintf('UID:res-%s@%s', $reservation->getId(), self::UID_DOMAIN),
            'SUMMARY:' . $this->escape($property->getTitle() . ' — ' . $this->guestDisplayName($guest)),
            'DTSTART;VALUE=DATE:' . $checkin->format('Ymd'),
            'DTEND;VALUE=DATE:' . $checkout->format('Ymd'),
            'DESCRIPTION:' . $this->escape($description),
            'END:VEVENT',
        ];
    }

    private function guestDisplayName(?User $guest): string
    {
        $profile = $guest?->getProfile();
        $name = trim(sprintf('%s %s', $profile?->getFirstName() ?? '', $profile?->getLastName() ?? ''));

        return $name !== '' ? $name : ($guest?->getEmail() ?? '');
    }

    private function formatPrice(Reservation $reservation): string
    {
        $amount = $reservation->getTotalPrice() ?? '0';
        if (str_contains($amount, '.')) {
            $amount = rtrim(rtrim($amount, '0'), '.');
        }

        $symbol = match ($reservation->getCurrency()) {
            'USD' => '$',
            'GBP' => '£',
            default => '€',
        };

        return $amount . $symbol;
    }

    private function escape(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(';', '\;', $text);
        $text = str_replace(',', '\,', $text);

        return str_replace(["\r\n", "\n", "\r"], '\n', $text);
    }
}
