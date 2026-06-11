<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;

/**
 * Algorithme de disponibilité (sujet A.2).
 *
 * Une plage [checkin, checkout[ est disponible si et seulement si :
 *  1. le logement est publié ;
 *  2. aucun jour de la période n'a été bloqué manuellement par l'hôte ;
 *  3. aucune réservation confirmée ne chevauche la plage ;
 *  4. la capacité d'accueil est suffisante pour le nombre de voyageurs.
 */
final readonly class AvailabilityService
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private PropertyAvailabilityRepository $availabilityRepository,
    ) {
    }

    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
        ?Reservation $exclude = null,
    ): bool {
        return $this->check($property, $checkin, $checkout, $guests, $exclude)->available;
    }

    public function check(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
        ?Reservation $exclude = null,
    ): AvailabilityResult {
        $checkin = $this->normalize($checkin);
        $checkout = $this->normalize($checkout);
        $reasons = [];

        if ($checkout <= $checkin) {
            $reasons[] = 'La date de départ doit être postérieure à la date d\'arrivée.';

            return AvailabilityResult::unavailable($reasons);
        }

        if ($property->getStatus() !== 'published') {
            $reasons[] = 'Le logement n\'est pas publié.';
        }

        if ($guests < 1 || ($property->getMaxGuests() !== null && $guests > $property->getMaxGuests())) {
            $reasons[] = 'La capacité d\'accueil est insuffisante pour le nombre de voyageurs.';
        }

        if ($this->availabilityRepository->findBlockedDays($property, $checkin, $checkout) !== []) {
            $reasons[] = 'Une ou plusieurs nuits ont été bloquées par l\'hôte.';
        }

        if ($this->reservationRepository->findOverlappingConfirmed($property, $checkin, $checkout, $exclude) !== []) {
            $reasons[] = 'Ces dates sont déjà réservées.';
        }

        return $reasons === [] ? AvailabilityResult::available() : AvailabilityResult::unavailable($reasons);
    }

    private function normalize(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTime(0, 0, 0);
    }
}
