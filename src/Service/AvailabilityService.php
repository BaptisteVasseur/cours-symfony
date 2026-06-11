<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\PropertyBlockRepository;
use App\Repository\ReservationRepository;

class AvailabilityService
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly PropertyBlockRepository $blockRepository,
    ) {}

    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
    ): bool {
        if ($property->getStatus() !== 'published') {
            return false;
        }

        if ($guests > $property->getMaxGuests()) {
            return false;
        }

        if ($this->reservationRepository->countOverlapping($property, $checkin, $checkout) > 0) {
            return false;
        }

        if ($this->blockRepository->countOverlapping($property, $checkin, $checkout) > 0) {
            return false;
        }

        return true;
    }
}
