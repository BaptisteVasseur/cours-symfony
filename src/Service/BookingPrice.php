<?php

declare(strict_types=1);

namespace App\Service;

final readonly class BookingPrice
{
    public function __construct(
        public int $nights,
        public string $subtotal,
        public string $cleaningFee,
        public string $serviceFee,
        public string $securityDeposit,
        public string $total,
        public string $currency,
    ) {
    }
}
