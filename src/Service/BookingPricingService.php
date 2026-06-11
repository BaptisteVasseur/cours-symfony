<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\PropertyAvailabilityRepository;

final readonly class BookingPricingService
{
    public function __construct(
        private PropertyAvailabilityRepository $propertyAvailabilityRepository,
    ) {
    }

    public function calculate(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): BookingPricingResult {
        $entries = $this->propertyAvailabilityRepository->findEntriesForPeriod($property, $checkin, $checkout);
        $ratesByDay = [];

        foreach ($entries as $entry) {
            if ($entry->getPriceOverride() === null) {
                continue;
            }

            $ratesByDay[$entry->getAvailableDate()?->format('Y-m-d') ?? ''] = (float) $entry->getPriceOverride();
        }

        $subtotal = 0.0;
        $cursor = $checkin;
        while ($cursor < $checkout) {
            $subtotal += $ratesByDay[$cursor->format('Y-m-d')] ?? (float) $property->getPricePerNight();
            $cursor = $cursor->modify('+1 day');
        }

        $nights = (int) $checkin->diff($checkout)->days;
        $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
        $serviceFee = round($subtotal * 0.12, 2);
        $totalPrice = round($subtotal + $cleaningFee + $serviceFee, 2);

        return new BookingPricingResult(
            $nights,
            round($subtotal, 2),
            $cleaningFee,
            $serviceFee,
            $totalPrice,
        );
    }
}
