<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;

/**
 * Algorithme de disponibilité (A.2).
 *
 * Une plage de dates [checkin, checkout) est disponible si et seulement si :
 *  1. le logement est publié ;
 *  2. aucune nuit de la période n'a été manuellement bloquée par l'hôte ;
 *  3. aucune réservation confirmée ne se superpose à ces dates ;
 *  4. la capacité d'accueil est suffisante pour le nombre de voyageurs.
 */
final class AvailabilityChecker
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly PropertyAvailabilityRepository $availabilityRepository,
    ) {
    }

    public function isAvailable(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout, int $guests): bool
    {
        return $this->check($property, $checkin, $checkout, $guests)->isAvailable();
    }

    public function check(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout, int $guests): AvailabilityStatus
    {
        $checkin = $checkin->setTime(0, 0);
        $checkout = $checkout->setTime(0, 0);

        if ($checkout <= $checkin) {
            return AvailabilityStatus::InvalidRange;
        }

        if ($property->getStatus() !== 'published') {
            return AvailabilityStatus::NotPublished;
        }

        if ($guests < 1 || $guests > (int) $property->getMaxGuests()) {
            return AvailabilityStatus::InsufficientCapacity;
        }

        // Nuits occupées : [checkin, checkout). La dernière nuit est checkout - 1 jour.
        $lastNight = $checkout->modify('-1 day');
        if ($this->availabilityRepository->findOverlappingBlock($property, $checkin, $lastNight) !== null) {
            return AvailabilityStatus::Blocked;
        }

        if ($this->reservationRepository->findConfirmedConflict($property, $checkin, $checkout) !== null) {
            return AvailabilityStatus::Reserved;
        }

        return AvailabilityStatus::Available;
    }
}
