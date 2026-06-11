<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;

final class AvailabilityService
{
    public function __construct(
        private readonly ReservationRepository $reservations,
        private readonly PropertyAvailabilityRepository $availabilities,
    ) {
    }

    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkinDate,
        \DateTimeImmutable $checkoutDate,
        int $guests,
        ?Reservation $exclude = null,
    ): bool {
        if ($checkinDate >= $checkoutDate) {
            return false;
        }

        if ($property->getStatus() !== 'published') {
            return false;
        }

        if ($guests < 1 || $guests > (int) $property->getMaxGuests()) {
            return false;
        }

        if ($this->reservations->hasConfirmedOverlap($property, $checkinDate, $checkoutDate, $exclude)) {
            return false;
        }

        return !$this->availabilities->hasBlockedDay($property, $checkinDate, $checkoutDate);
    }
}
