<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;

final class AvailabilityChecker
{
    public function __construct(
        private readonly ReservationRepository $reservations,
        private readonly PropertyAvailabilityRepository $availabilities,
    ) {
    }

    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
        ?Reservation $exclude = null,
    ): bool {
        $checkin = $checkin->setTime(0, 0);
        $checkout = $checkout->setTime(0, 0);

        if ($checkout <= $checkin) {
            return false;
        }

        if ($property->getStatus() !== 'published') {
            return false;
        }

        if ($guests < 1 || $guests > (int) $property->getMaxGuests()) {
            return false;
        }

        if ($this->availabilities->hasBlockedDateInRange($property, $checkin, $checkout)) {
            return false;
        }

        if ($this->reservations->hasConfirmedOverlap($property, $checkin, $checkout, $exclude)) {
            return false;
        }

        return true;
    }
}
