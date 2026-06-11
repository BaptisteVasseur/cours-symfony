<?php

declare(strict_types=1);

namespace App\Service\Booking;

final readonly class BookingPriceBreakdown
{
    public function __construct(
        public int $nights,
        public float $nightsAmount,
        public float $cleaningFee,
        public float $serviceFee,
        public float $securityDeposit,
        public float $totalAmount,
        public string $currency,
    ) {
    }
}
