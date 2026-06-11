<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;

class AvailabilityChecker
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly PropertyAvailabilityRepository $availabilityRepository,
    ) {
    }

    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests = 1,
    ): bool {
        // Règle 1 : le logement doit être publié
        if ($property->getStatus() !== 'published') {
            return false;
        }

        // Règle 2 : dates cohérentes (arrivée strictement avant départ)
        if ($checkin >= $checkout) {
            return false;
        }

        // Règle 3 : la capacité doit suffire
        if ($guests < 1 || $guests > (int) $property->getMaxGuests()) {
            return false;
        }

        // Règle 4 : aucun jour bloqué par l'hôte sur la période
        if ($this->availabilityRepository->countBlockedDays($property, $checkin, $checkout) > 0) {
            return false;
        }

        // Règle 5 : aucune réservation confirmée qui chevauche
        if ($this->reservationRepository->countOverlappingConfirmed($property, $checkin, $checkout) > 0) {
            return false;
        }

        return true;
    }
}