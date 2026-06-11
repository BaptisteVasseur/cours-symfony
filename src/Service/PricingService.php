<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\PropertyAvailabilityRepository;

final class PricingService
{
    private const float SERVICE_FEE_RATE = 0.12;

    public function __construct(
        private readonly PropertyAvailabilityRepository $availabilityRepository,
    ) {
    }

    /**
     * @return array{
     *     nights: int,
     *     nightlyRate: float,
     *     subtotal: float,
     *     cleaningFee: float,
     *     serviceFee: float,
     *     totalPrice: float,
     * }
     */
    public function calculate(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): array
    {
        $nights = (int) $checkin->diff($checkout)->days;
        $defaultRate = (float) $property->getPricePerNight();
        $overrides = $this->availabilityRepository->findPriceOverridesInRange($property, $checkin, $checkout);

        $subtotal = 0.0;
        $current = $checkin;
        while ($current < $checkout) {
            $key = $current->format('Y-m-d');
            $subtotal += isset($overrides[$key]) ? (float) $overrides[$key] : $defaultRate;
            $current = $current->modify('+1 day');
        }

        $averageNightly = $nights > 0 ? $subtotal / $nights : $defaultRate;
        $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
        $serviceFee = round($subtotal * self::SERVICE_FEE_RATE, 2);
        $totalPrice = round($subtotal + $cleaningFee + $serviceFee, 2);

        return [
            'nights' => $nights,
            'nightlyRate' => round($averageNightly, 2),
            'subtotal' => round($subtotal, 2),
            'cleaningFee' => $cleaningFee,
            'serviceFee' => $serviceFee,
            'totalPrice' => $totalPrice,
        ];
    }
}
