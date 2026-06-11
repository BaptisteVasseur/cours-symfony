<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;

class AvailabilityService
{
    public function __construct(
        private readonly PropertyAvailabilityRepository $availabilityRepository,
        private readonly ReservationRepository $reservationRepository,
    ) {}

    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guestsCount,
    ): bool {
        if ($property->getStatus() !== 'published') {
            return false;
        }

        if ($guestsCount > $property->getMaxGuests()) {
            return false;
        }

        if ($this->availabilityRepository->hasBlockedDayInRange($property, $checkin, $checkout)) {
            return false;
        }

        if ($this->reservationRepository->findConfirmedOrPendingOverlapping($property, $checkin, $checkout)) {
            return false;
        }

        return true;
    }
}
