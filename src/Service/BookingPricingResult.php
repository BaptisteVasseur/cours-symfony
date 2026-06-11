<?php

declare(strict_types=1);

namespace App\Service;

final readonly class BookingPricingResult
{
    public function __construct(
        private int $nights,
        private float $subtotal,
        private float $cleaningFee,
        private float $serviceFee,
        private float $totalPrice,
    ) {
    }

    public function getNights(): int
    {
        return $this->nights;
    }

    public function getSubtotal(): float
    {
        return $this->subtotal;
    }

    public function getCleaningFee(): float
    {
        return $this->cleaningFee;
    }

    public function getServiceFee(): float
    {
        return $this->serviceFee;
    }

    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }
}
