<?php

declare(strict_types=1);

namespace App\Service\Booking;

final readonly class BookingQuote
{
    public function __construct(
        public int $nights,
        public float $subtotal,
        public float $cleaningFee,
        public float $serviceFee,
        public float $total,
        public ?float $securityDeposit,
        public string $currency,
    ) {
    }
}
