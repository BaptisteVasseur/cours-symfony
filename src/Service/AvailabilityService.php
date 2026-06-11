<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;

final class AvailabilityService
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly PropertyAvailabilityRepository $availabilityRepository,
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

        if ($guestsCount > ($property->getMaxGuests() ?? 0)) {
            return false;
        }

        $propertyId = (string) $property->getId();

        if ($this->reservationRepository->countOverlapping($propertyId, $checkin, $checkout) > 0) {
            return false;
        }

        if ($this->availabilityRepository->hasBlockedDays($propertyId, $checkin, $checkout)) {
            return false;
        }

        return true;
    }
}
