<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\Property;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;

final class PropertyAvailabilityCalendarBuilder
{
    public function __construct(
        private PropertyAvailabilityRepository $propertyAvailabilityRepository,
        private ReservationRepository $reservationRepository,
    ) {
    }

    public function build(Property $property, \DateTimeImmutable $monthStart): array
    {
        $monthStart = $monthStart->modify('first day of this month');
        $monthEnd = $monthStart->modify('last day of this month');
        $gridStart = $monthStart->modify('monday this week');
        $gridEnd = $monthEnd->modify('sunday this week');
        $gridEndExclusive = $gridEnd->modify('+1 day');

        $availabilityOverrides = $this->propertyAvailabilityRepository->findForRange(
            $property,
            $gridStart,
            $gridEndExclusive,
        );

        $confirmedReservations = $this->reservationRepository->findConfirmedForPropertyRange(
            $property,
            $gridStart,
            $gridEndExclusive,
        );

        $overridesByDate = [];
        foreach ($availabilityOverrides as $availabilityOverride) {
            $date = $availabilityOverride->getAvailableDate();
            if ($date === null) {
                continue;
            }

            $overridesByDate[$date->format('Y-m-d')] = $availabilityOverride;
        }

        $bookingsByDate = [];
        foreach ($confirmedReservations as $reservation) {
            $checkin = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();

            if ($checkin === null || $checkout === null) {
                continue;
            }

            $cursor = self::maxDate($checkin, $gridStart);
            $end = self::minDate($checkout, $gridEndExclusive);

            while ($cursor < $end) {
                $bookingsByDate[$cursor->format('Y-m-d')][] = $reservation;
                $cursor = $cursor->modify('+1 day');
            }
        }

        $today = new \DateTimeImmutable('today');
        $weeks = [];
        $week = [];
        $summary = [
            'available' => 0,
            'blocked' => 0,
            'booked' => 0,
            'customPrice' => 0,
            'minimumStay' => 0,
        ];

        $cursor = $gridStart;
        while ($cursor <= $gridEnd) {
            $dateKey = $cursor->format('Y-m-d');
            $override = $overridesByDate[$dateKey] ?? null;
            $bookings = $bookingsByDate[$dateKey] ?? [];
            $hasPriceOverride = $override !== null && $override->getPriceOverride() !== null;
            $minimumStay = $override?->getMinimumStay();
            $isCurrentMonth = $cursor->format('Y-m') === $monthStart->format('Y-m');

            if ($bookings !== []) {
                $state = 'booked';
            } elseif ($override !== null && !$override->isAvailable()) {
                $state = 'blocked';
            } else {
                $state = 'available';
            }

            if ($isCurrentMonth) {
                $summary[$state]++;

                if ($hasPriceOverride) {
                    $summary['customPrice']++;
                }

                if ($minimumStay !== null) {
                    $summary['minimumStay']++;
                }
            }

            $week[] = [
                'date' => $cursor,
                'state' => $state,
                'isCurrentMonth' => $isCurrentMonth,
                'isToday' => $cursor->format('Y-m-d') === $today->format('Y-m-d'),
                'price' => $hasPriceOverride ? (float) $override->getPriceOverride() : (float) $property->getPricePerNight(),
                'hasPriceOverride' => $hasPriceOverride,
                'minimumStay' => $minimumStay,
                'bookings' => $bookings,
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }

            $cursor = $cursor->modify('+1 day');
        }

        return [
            'weeks' => $weeks,
            'summary' => $summary,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
            'previousMonth' => $monthStart->modify('-1 month'),
            'nextMonth' => $monthStart->modify('+1 month'),
            'confirmedReservations' => $confirmedReservations,
        ];
    }

    private static function maxDate(\DateTimeImmutable $left, \DateTimeImmutable $right): \DateTimeImmutable
    {
        return $left >= $right ? $left : $right;
    }

    private static function minDate(\DateTimeImmutable $left, \DateTimeImmutable $right): \DateTimeImmutable
    {
        return $left <= $right ? $left : $right;
    }
}
