<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;

class PropertyAvailabilityService
{
    public function __construct(
        private readonly PropertyAvailabilityRepository $propertyAvailabilityRepository,
        private readonly ReservationRepository $reservationRepository,
    ) {
    }

    // Check if a property is available for a given date range and number of guests.
    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkinDate,
        \DateTimeImmutable $checkoutDate,
        int $guestsCount
    ): bool {
        if ($property->getStatus() !== 'published') {
            return false;
        }

        if ($guestsCount <= 0) {
            return false;
        }

        if ($property->getMaxGuests() === null || $property->getMaxGuests() < $guestsCount) {
            return false;
        }

        if ($checkoutDate <= $checkinDate) {
            return false;
        }

        $unavailableDaysCount = $this->propertyAvailabilityRepository->countUnavailableDaysBetween(
            $property,
            $checkinDate,
            $checkoutDate
        );

        if ($unavailableDaysCount > 0) {
            return false;
        }

        if ($this->reservationRepository->hasConfirmedReservationOverlap($property, $checkinDate, $checkoutDate)) {
            return false;
        }

        return true;
    }
}
