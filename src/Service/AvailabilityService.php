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
        private readonly PropertyAvailabilityRepository $propertyAvailabilityRepository,
    ) {
    }

    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
    ): bool {
        if ($property->getStatus() !== 'published') {
            return false;
        }

        if (($property->getMaxGuests() ?? 0) < $guests) {
            return false;
        }

        if ($this->reservationRepository->countOverlappingConfirmed($property, $checkin, $checkout) > 0) {
            return false;
        }

        return $this->propertyAvailabilityRepository->countUnavailableDays($property, $checkin, $checkout) === 0;
    }
}
