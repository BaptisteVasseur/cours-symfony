<?php

namespace App\Service;

use App\Entity\Property;
use App\Enum\PropertyStatus;
use App\Repository\BookingRepository;
use App\Repository\PropertyAvailabilityRepository;

class AvailabilityService
{
    public function __construct(
        private readonly BookingRepository $bookingRepo,
        private readonly PropertyAvailabilityRepository $availabilityRepo,
    ) {}

    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
        int $guestsCount,
    ): bool {
        if ($property->getStatus() !== PropertyStatus::PUBLISHED) {
            return false;
        }

        if ($guestsCount > $property->getMaxGuests()) {
            return false;
        }

        if ($checkOut <= $checkIn) {
            return false;
        }

        // Only CONFIRMED bookings block dates (PENDING does not — see conception.txt)
        if ($this->bookingRepo->hasConfirmedConflict($checkIn, $checkOut, $property)) {
            return false;
        }

        if ($this->availabilityRepo->hasBlockedConflict($checkIn, $checkOut, $property)) {
            return false;
        }

        return true;
    }

    /**
     * Returns all dates blocked for a property in a given month as an associative map
     * [ 'Y-m-d' => 'confirmed'|'pending'|'blocked' ]
     */
    public function getCalendarStates(Property $property, int $year, int $month): array
    {
        $states = [];

        $firstDay = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $daysInMonth = (int) $firstDay->format('t');

        $confirmedBookings = $this->bookingRepo->findConfirmedForProperty($property);
        $blockedPeriods = $this->availabilityRepo->findFutureForProperty($property);

        // Also fetch pending bookings for the calendar display
        $pendingBookings = $this->bookingRepo->findPendingForHost($property->getHost() ?? new \App\Entity\User());

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $d));
            $key = $date->format('Y-m-d');
            $state = 'available';

            foreach ($confirmedBookings as $booking) {
                if ($date >= $booking->getCheckIn() && $date < $booking->getCheckOut()) {
                    $state = 'confirmed';
                    break;
                }
            }

            if ($state === 'available') {
                foreach ($pendingBookings as $booking) {
                    if ($booking->getProperty() === $property
                        && $date >= $booking->getCheckIn()
                        && $date < $booking->getCheckOut()) {
                        $state = 'pending';
                        break;
                    }
                }
            }

            if ($state === 'available') {
                foreach ($blockedPeriods as $period) {
                    if ($date >= $period->getStartDate() && $date < $period->getEndDate()) {
                        $state = 'blocked';
                        break;
                    }
                }
            }

            $states[$key] = $state;
        }

        return $states;
    }
}
