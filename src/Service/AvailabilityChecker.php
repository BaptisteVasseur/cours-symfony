<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Repository\PropertyUnavailabilityRepository;
use App\Repository\ReservationRepository;

/**
 * Service to check availability of a property for given dates.
 *
 * Based on the conception.txt requirements:
 * A date range is available if and only if:
 * 1. Property status is 'published'
 * 2. No manual blocking by the host
 * 3. No confirmed reservations overlap
 * 4. Property has enough capacity for guest count
 *
 * Performance optimization: Single SQL query to check all reservations overlapping with date range
 */
class AvailabilityChecker
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly PropertyUnavailabilityRepository $unavailabilityRepository,
    ) {
    }

    /**
     * Check if a property is available for the given date range.
     *
     * @throws \InvalidArgumentException If dates are invalid
     */
    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkinDate,
        \DateTimeImmutable $checkoutDate,
        int $guestsCount,
        ?Reservation $excludeReservation = null
    ): bool {
        // Validate inputs
        if ($checkinDate >= $checkoutDate) {
            throw new \InvalidArgumentException('Checkout date must be after checkin date.');
        }

        if ($guestsCount < 1) {
            throw new \InvalidArgumentException('Guest count must be at least 1.');
        }

        // 1. Check property status
        if ($property->getStatus() !== 'published') {
            return false;
        }

        // 2. Check capacity
        if ($guestsCount > $property->getMaxGuests()) {
            return false;
        }

        // 3. Check unavailability blocks (host-defined periods)
        if ($this->unavailabilityRepository->hasUnavailabilityBetween($property, $checkinDate, $checkoutDate)) {
            return false;
        }

        // 4. Check for overlapping confirmed reservations (optimized single query)
        return !$this->hasOverlappingReservations($property, $checkinDate, $checkoutDate, $excludeReservation);
    }

    /**
     * Get availability details for a property including conflicts.
     *
     * @return array<string, mixed>
     */
    public function getAvailabilityDetails(
        Property $property,
        \DateTimeImmutable $checkinDate,
        \DateTimeImmutable $checkoutDate,
        int $guestsCount,
        ?Reservation $excludeReservation = null
    ): array {
        $details = [
            'isAvailable' => false,
            'conflicts' => [],
            'reasons' => [],
        ];

        // Check each condition and collect reasons
        if ($property->getStatus() !== 'published') {
            $details['reasons'][] = 'Property is not published.';

            return $details;
        }

        if ($guestsCount > $property->getMaxGuests()) {
            $details['reasons'][] = sprintf(
                'Property accepts maximum %d guests, but %d are requested.',
                $property->getMaxGuests(),
                $guestsCount
            );

            return $details;
        }

        // Check unavailability blocks
        $unavailableBlocks = $this->unavailabilityRepository->findOverlappingUnavailability(
            $property,
            $checkinDate,
            $checkoutDate
        );

        if (!empty($unavailableBlocks)) {
            $details['conflicts'] = array_map(
                static fn ($block) => [
                    'type' => 'unavailability',
                    'reason' => $block->getReason(),
                    'startDate' => $block->getStartDate(),
                    'endDate' => $block->getEndDate(),
                ],
                $unavailableBlocks
            );
            $details['reasons'][] = 'Property has unavailability blocks during this period.';

            return $details;
        }

        // Check for overlapping reservations
        $overlappingReservations = $this->getOverlappingReservations(
            $property,
            $checkinDate,
            $checkoutDate,
            $excludeReservation
        );

        if (!empty($overlappingReservations)) {
            $details['conflicts'] = array_map(
                static fn ($reservation) => [
                    'type' => 'reservation',
                    'status' => $reservation->getStatus(),
                    'checkinDate' => $reservation->getCheckinDate(),
                    'checkoutDate' => $reservation->getCheckoutDate(),
                    'guestName' => $reservation->getGuest()?->getUserIdentifier(),
                ],
                $overlappingReservations
            );
            $details['reasons'][] = 'Property has confirmed reservations during this period.';

            return $details;
        }

        $details['isAvailable'] = true;

        return $details;
    }

    /**
     * Check if there are overlapping confirmed reservations.
     * Optimized for performance with a single SQL query.
     */
    private function hasOverlappingReservations(
        Property $property,
        \DateTimeImmutable $checkinDate,
        \DateTimeImmutable $checkoutDate,
        ?Reservation $excludeReservation = null
    ): bool {
        return $this->reservationRepository->countOverlappingReservations(
            $property,
            $checkinDate,
            $checkoutDate,
            $excludeReservation
        ) > 0;
    }

    /**
     * Get overlapping confirmed reservations.
     *
     * @return list<Reservation>
     */
    private function getOverlappingReservations(
        Property $property,
        \DateTimeImmutable $checkinDate,
        \DateTimeImmutable $checkoutDate,
        ?Reservation $excludeReservation = null
    ): array {
        return $this->reservationRepository->findOverlappingReservations(
            $property,
            $checkinDate,
            $checkoutDate,
            $excludeReservation
        );
    }

    /**
     * Get the next available date for booking.
     */
    public function getNextAvailableDate(Property $property, \DateTimeImmutable $fromDate): ?\DateTimeImmutable
    {
        if ($property->getStatus() !== 'published') {
            return null;
        }

        // Start checking from the given date
        $currentDate = $fromDate;
        $maxDaysToCheck = 365; // Check up to one year ahead

        for ($i = 0; $i < $maxDaysToCheck; ++$i) {
            $checkoutDate = $currentDate->modify('+1 day');

            // Try a single night stay
            if ($this->isAvailable($property, $currentDate, $checkoutDate, 1)) {
                return $currentDate;
            }

            $currentDate = $checkoutDate;
        }

        return null;
    }

    /**
     * Get all blocked dates in a date range (unavailability + confirmed reservations).
     *
     * @return list<\DateTimeImmutable>
     */
    public function getBlockedDates(
        Property $property,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        $blockedDates = [];

        // Get unavailability blocks
        $unavailableBlocks = $this->unavailabilityRepository->findOverlappingUnavailability(
            $property,
            $startDate,
            $endDate
        );

        foreach ($unavailableBlocks as $block) {
            $current = $block->getStartDate();
            while ($current < $block->getEndDate()) {
                $blockedDates[] = $current;
                $current = $current->modify('+1 day');
            }
        }

        // Get confirmed reservations
        $confirmedReservations = $this->reservationRepository->findOverlappingReservations(
            $property,
            $startDate,
            $endDate,
            null
        );

        foreach ($confirmedReservations as $reservation) {
            $current = $reservation->getCheckinDate();
            while ($current < $reservation->getCheckoutDate()) {
                $blockedDates[] = $current;
                $current = $current->modify('+1 day');
            }
        }

        // Return unique dates, sorted
        $uniqueDates = array_unique(
            array_map(static fn ($date) => $date->format('Y-m-d'), $blockedDates)
        );
        sort($uniqueDates);

        return array_map(
            static fn ($dateStr) => \DateTimeImmutable::createFromFormat('Y-m-d', $dateStr) ?: $startDate,
            $uniqueDates
        );
    }
}
