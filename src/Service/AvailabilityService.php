<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Listing;
use App\Repository\AvailabilityBlockRepository;
use App\Repository\BookingRepository;
use App\ValueObject\DateRange;

final class AvailabilityService
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly AvailabilityBlockRepository $blockRepository,
    ) {
    }

    public function isAvailable(Listing $listing, DateRange $range, int $guests = 1, ?Booking $exclude = null): bool
    {
        if (!$listing->isPublished()) {
            return false;
        }

        if ($guests > 0 && $listing->getMaxGuests() !== null && $guests > $listing->getMaxGuests()) {
            return false;
        }

        if ($this->blockRepository->hasBlockOverlap($listing, $range->checkIn, $range->checkOut)) {
            return false;
        }

        if ($this->bookingRepository->hasConfirmedOverlap($listing, $range->checkIn, $range->checkOut, $exclude)) {
            return false;
        }

        return true;
    }

    public function unavailableReason(Listing $listing, DateRange $range, int $guests = 1): ?string
    {
        if (!$listing->isPublished()) {
            return 'Ce logement n\'est pas publié.';
        }
        if ($guests > 0 && $listing->getMaxGuests() !== null && $guests > $listing->getMaxGuests()) {
            return sprintf('Ce logement accueille au maximum %d voyageur(s).', $listing->getMaxGuests());
        }
        if (
            $this->blockRepository->hasBlockOverlap($listing, $range->checkIn, $range->checkOut)
            || $this->bookingRepository->hasConfirmedOverlap($listing, $range->checkIn, $range->checkOut)
        ) {
            return 'Ces dates ne sont pas disponibles.';
        }

        return null;
    }
}
