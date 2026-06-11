<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;

final class PricingCalculator
{
    public const SERVICE_FEE_RATE = 0.12;

    public function calculate(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): array
    {
        $nights = (int) $checkin->diff($checkout)->days;
        $nightlyRate = (float) $property->getPricePerNight();
        $subtotal = $nightlyRate * $nights;
        $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
        $serviceFee = round($subtotal * self::SERVICE_FEE_RATE, 2);
        $total = round($subtotal + $cleaningFee + $serviceFee, 2);

        return [
            'nights' => $nights,
            'nightlyRate' => $nightlyRate,
            'subtotal' => round($subtotal, 2),
            'cleaningFee' => $cleaningFee,
            'serviceFee' => $serviceFee,
            'total' => $total,
        ];
    }
}
