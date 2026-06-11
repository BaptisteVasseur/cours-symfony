<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;

final class PriceCalculator
{
    private const SERVICE_FEE_RATE = 0.10;

    /**
     * @return array{nights: int, subtotal: float, cleaningFee: float, serviceFee: float, total: float}
     */
    public function compute(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): array
    {
        $checkin = $checkin->setTime(0, 0);
        $checkout = $checkout->setTime(0, 0);

        $nights = (int) $checkin->diff($checkout)->days;
        if ($nights < 1) {
            $nights = 1;
        }

        $pricePerNight = (float) $property->getPricePerNight();
        $subtotal = round($nights * $pricePerNight, 2);
        $cleaningFee = round((float) $property->getCleaningFee(), 2);
        $serviceFee = round($subtotal * self::SERVICE_FEE_RATE, 2);
        $total = round($subtotal + $cleaningFee + $serviceFee, 2);

        return [
            'nights' => $nights,
            'subtotal' => $subtotal,
            'cleaningFee' => $cleaningFee,
            'serviceFee' => $serviceFee,
            'total' => $total,
        ];
    }
}
