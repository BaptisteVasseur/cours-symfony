<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;

final readonly class BookingAvailabilityService
{
    public const string PROPERTY_NOT_PUBLISHED = 'property_not_published';
    public const string INVALID_DATE_RANGE = 'invalid_date_range';
    public const string GUEST_CAPACITY_EXCEEDED = 'guest_capacity_exceeded';
    public const string DATES_ALREADY_BOOKED = 'dates_already_booked';
    public const string DATES_MANUALLY_BLOCKED = 'dates_manually_blocked';
    public const string MINIMUM_STAY_NOT_MET = 'minimum_stay_not_met';

    public function __construct(
        private ReservationRepository $reservationRepository,
        private PropertyAvailabilityRepository $propertyAvailabilityRepository,
    ) {
    }

    public function check(
        Property $property,
        \DateTimeInterface $checkin,
        \DateTimeInterface $checkout,
        int $guestsCount,
        ?Reservation $excludedReservation = null,
    ): BookingAvailabilityResult {
        $checkin = $checkin instanceof \DateTimeImmutable ? $checkin : \DateTimeImmutable::createFromInterface($checkin);
        $checkout = $checkout instanceof \DateTimeImmutable ? $checkout : \DateTimeImmutable::createFromInterface($checkout);

        if ($property->getStatus() !== 'published') {
            return BookingAvailabilityResult::unavailable([self::PROPERTY_NOT_PUBLISHED]);
        }

        if ($checkin >= $checkout) {
            return BookingAvailabilityResult::unavailable([self::INVALID_DATE_RANGE]);
        }

        $maxGuests = $property->getMaxGuests();
        if ($maxGuests !== null && $guestsCount > $maxGuests) {
            return BookingAvailabilityResult::unavailable([self::GUEST_CAPACITY_EXCEEDED]);
        }

        if ($this->reservationRepository->existsConfirmedOverlap($property, $checkin, $checkout, $excludedReservation)) {
            return BookingAvailabilityResult::unavailable([self::DATES_ALREADY_BOOKED]);
        }

        $entries = $this->propertyAvailabilityRepository->findEntriesForPeriod($property, $checkin, $checkout);
        foreach ($entries as $entry) {
            if (!$entry->isAvailable()) {
                return BookingAvailabilityResult::unavailable([self::DATES_MANUALLY_BLOCKED]);
            }
        }

        $nights = (int) $checkin->diff($checkout)->days;
        $minimumStay = 1;
        foreach ($entries as $entry) {
            $entryMinimumStay = $entry->getMinimumStay();
            if ($entryMinimumStay !== null && $entryMinimumStay > $minimumStay) {
                $minimumStay = $entryMinimumStay;
            }
        }

        if ($nights < $minimumStay) {
            return BookingAvailabilityResult::unavailable([self::MINIMUM_STAY_NOT_MET]);
        }

        return BookingAvailabilityResult::available();
    }
}
