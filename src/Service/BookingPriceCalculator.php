<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\PropertyAvailabilityRepository;

final readonly class BookingPriceCalculator
{
    public function __construct(
        private PropertyAvailabilityRepository $availabilityRepository,
    ) {
    }

    public function calculate(Property $property, \DateTimeImmutable $checkinDate, \DateTimeImmutable $checkoutDate): BookingPrice
    {
        $propertyId = (string) $property->getId();
        $nights = $this->countNights($checkinDate, $checkoutDate);
        $overrides = [];

        foreach ($this->availabilityRepository->findForPriceCalculation($propertyId, $checkinDate, $checkoutDate) as $availability) {
            if ($availability->getPriceOverride() !== null) {
                $overrides[$availability->getAvailableDate()?->format('Y-m-d')] = (float) $availability->getPriceOverride();
            }
        }

        $subtotal = 0.0;
        $current = $checkinDate;
        while ($current < $checkoutDate) {
            $subtotal += $overrides[$current->format('Y-m-d')] ?? (float) $property->getPricePerNight();
            $current = $current->modify('+1 day');
        }

        $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
        $securityDeposit = (float) ($property->getSecurityDeposit() ?? 0);
        $serviceFee = round($subtotal * 0.12, 2);
        $total = $subtotal + $cleaningFee + $serviceFee + $securityDeposit;

        return new BookingPrice(
            $nights,
            number_format($subtotal, 2, '.', ''),
            number_format($cleaningFee, 2, '.', ''),
            number_format($serviceFee, 2, '.', ''),
            number_format($securityDeposit, 2, '.', ''),
            number_format($total, 2, '.', ''),
            'EUR',
        );
    }

    public function countNights(\DateTimeImmutable $checkinDate, \DateTimeImmutable $checkoutDate): int
    {
        return (int) $checkinDate->diff($checkoutDate)->days;
    }
}
