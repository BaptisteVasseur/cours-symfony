<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;

final class AvailabilityService
{
    public function __construct(
        private PropertyAvailabilityRepository $availabilityRepository,
        private ReservationRepository $reservationRepository,
    ) {}

    /**
     * Vérifie les 4 conditions du sujet (algo A2) en 2 requêtes SQL max.
     */
    public function isAvailable(
        Property $property,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        int $guests,
        ?string $excludeReservationId = null,
    ): bool {
        if ($property->getStatus() !== 'published') {
            return false;
        }

        if ($property->getMaxGuests() < $guests) {
            return false;
        }

        if ($this->availabilityRepository->hasBlockedDatesInRange($property, $start, $end)) {
            return false;
        }

        if ($this->reservationRepository->hasConfirmedOverlap($property, $start, $end, $excludeReservationId)) {
            return false;
        }

        return true;
    }
}
