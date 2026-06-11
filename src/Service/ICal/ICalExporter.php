<?php

declare(strict_types=1);

namespace App\Service\ICal;

use App\Entity\Property;
use App\Entity\Reservation;
use Sabre\VObject\Component\VCalendar;

/**
 * Génère le flux iCal (RFC 5545) d'un logement : un VEVENT « journée entière »
 * par séjour confirmé (énoncé §Partie E). sabre/vobject gère le pliage de lignes,
 * les CRLF et l'échappement. Les dates sont en VALUE=DATE ; DTEND correspond au
 * jour de départ (borne exclusive du standard iCal, cohérent avec notre
 * intervalle demi-ouvert).
 */
final class ICalExporter
{
    /**
     * @param iterable<Reservation> $confirmedReservations
     */
    public function export(Property $property, iterable $confirmedReservations): string
    {
        $calendar = new VCalendar([
            'PRODID' => '-//Clone Airbnb//FR',
            'VERSION' => '2.0',
        ]);

        foreach ($confirmedReservations as $reservation) {
            if ($reservation->getStatus() !== 'confirmed') {
                continue;
            }

            $checkin = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();
            if ($checkin === null || $checkout === null) {
                continue;
            }

            $nights = (int)$checkin->diff($checkout)->days;
            $guest = $reservation->getGuest();
            $guestName = $this->guestName($reservation);

            $event = $calendar->add('VEVENT', [
                'UID' => sprintf('res-%s@clone-airbnb.local', $reservation->getId()),
                'SUMMARY' => sprintf('%s — %s', (string)$property->getTitle(), $guestName),
                'DESCRIPTION' => sprintf(
                    'Séjour %d nuit%s — %s%s — %s',
                    $nights,
                    $nights > 1 ? 's' : '',
                    $reservation->getTotalPrice(),
                    $reservation->getCurrency() === 'EUR' ? '€' : ' ' . $reservation->getCurrency(),
                    (string)$guest?->getEmail(),
                ),
            ]);

            // VALUE=DATE : valeur littérale AAAAMMJJ, sans composante horaire.
            $dtStart = $event->add('DTSTART', $checkin->format('Ymd'));
            $dtStart['VALUE'] = 'DATE';
            $dtEnd = $event->add('DTEND', $checkout->format('Ymd'));
            $dtEnd['VALUE'] = 'DATE';
        }

        return $calendar->serialize();
    }

    private function guestName(Reservation $reservation): string
    {
        $guest = $reservation->getGuest();
        if ($guest === null) {
            return 'Voyageur';
        }

        $profile = $guest->getProfile();
        if ($profile !== null && ($profile->getFirstName() || $profile->getLastName())) {
            return trim($profile->getFirstName() . ' ' . $profile->getLastName());
        }

        return (string)$guest->getEmail();
    }
}
