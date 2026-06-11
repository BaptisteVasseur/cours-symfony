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

    /**
     * Returns true if the date range [checkin, checkout) is fully available.
     * Checks guest capacity, no confirmed reservation overlap, and no manually blocked dates.
     */
    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guestsCount,
    ): bool {
        if ($guestsCount > $property->getMaxGuests()) {
            return false;
        }

        if ($this->reservationRepository->findConfirmedOverlapping($property, $checkin, $checkout) !== []) {
            return false;
        }

        $blockedRows = $this->availabilityRepository->findBlockedInRange($property, $checkin, $checkout);
        if ($blockedRows !== []) {
            return false;
        }

        return true;
    }

    /**
     * Returns a map of 'Y-m-d' => bool (true = available) for every day in the given month.
     * Combines manually blocked dates and confirmed reservation ranges.
     *
     * @return array<string, bool>
     */
    public function getMonthCalendar(Property $property, int $year, int $month): array
    {
        $firstDay = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $lastDay = $firstDay->modify('first day of next month');

        $blockedRows = $this->availabilityRepository->findBlockedInRange($property, $firstDay, $lastDay);
        $blockedDates = [];
        foreach ($blockedRows as $row) {
            $blockedDates[$row->getAvailableDate()->format('Y-m-d')] = true;
        }

        $confirmedReservations = $this->reservationRepository->findConfirmedOverlapping($property, $firstDay, $lastDay);
        foreach ($confirmedReservations as $reservation) {
            $cursor = $reservation->getCheckinDate();
            $end = $reservation->getCheckoutDate();
            while ($cursor < $end) {
                $blockedDates[$cursor->format('Y-m-d')] = true;
                $cursor = $cursor->modify('+1 day');
            }
        }

        $calendar = [];
        $cursor = $firstDay;
        while ($cursor < $lastDay) {
            $key = $cursor->format('Y-m-d');
            $calendar[$key] = !isset($blockedDates[$key]);
            $cursor = $cursor->modify('+1 day');
        }

        return $calendar;
    }

    /**
     * Returns an array of unavailable date strings ('Y-m-d') for a property in a given month.
     *
     * @return list<string>
     */
    public function getUnavailableDatesForMonth(Property $property, int $year, int $month): array
    {
        $calendar = $this->getMonthCalendar($property, $year, $month);

        return array_keys(array_filter($calendar, static fn(bool $available) => !$available));
    }
}
