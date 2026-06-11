<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\Property;
use App\Repository\PropertyAvailabilityRepository;

final class BookingPricingService
{
    public function __construct(
        private PropertyAvailabilityRepository $propertyAvailabilityRepository,
    ) {
    }

    public function calculate(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): BookingQuote {
        $overrides = $this->propertyAvailabilityRepository->findForRange($property, $checkin, $checkout);
        $indexedOverrides = [];

        foreach ($overrides as $override) {
            $date = $override->getAvailableDate();

            if ($date === null) {
                continue;
            }

            $indexedOverrides[$date->format('Y-m-d')] = $override;
        }

        $subtotal = 0.0;
        $cursor = $checkin;
        $nights = 0;

        while ($cursor < $checkout) {
            $key = $cursor->format('Y-m-d');
            $nightPrice = isset($indexedOverrides[$key]) && $indexedOverrides[$key]->getPriceOverride() !== null
                ? (float) $indexedOverrides[$key]->getPriceOverride()
                : (float) $property->getPricePerNight();

            $subtotal += $nightPrice;
            $nights++;
            $cursor = $cursor->modify('+1 day');
        }

        $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
        $serviceFee = round($subtotal * 0.12, 2);
        $total = round($subtotal + $cleaningFee + $serviceFee, 2);
        $securityDeposit = $property->getSecurityDeposit() !== null ? (float) $property->getSecurityDeposit() : null;

        return new BookingQuote(
            $nights,
            round($subtotal, 2),
            $cleaningFee,
            $serviceFee,
            $total,
            $securityDeposit,
            'EUR',
        );
    }
}
