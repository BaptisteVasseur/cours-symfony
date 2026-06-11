<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;

final class AvailabilityService
{
    public function __construct(
        private readonly PropertyAvailabilityRepository $availabilityRepository,
        private readonly ReservationRepository $reservationRepository,
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

        if ($property->getMaxGuests() < $guests) {
            return false;
        }

        $blockedDates = $this->availabilityRepository->countBlockedInRange($property, $checkin, $checkout);
        if ($blockedDates > 0) {
            return false;
        }

        $overlapping = $this->reservationRepository->countOverlappingConfirmed($property, $checkin, $checkout);
        if ($overlapping > 0) {
            return false;
        }

        return true;
    }
}
