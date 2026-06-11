<?php

declare(strict_types=1);

namespace App\Dto;

final class PriceQuote
{
    public function __construct(
        public readonly int $nights,
        public readonly string $pricePerNight,
        public readonly string $baseAmount,
        public readonly string $cleaningFee,
        public readonly string $serviceFee,
        public readonly string $taxesAmount,
        public readonly string $totalAmount,
        public readonly string $currency,
    ) {
    }

    public function toArray(): array
    {
        return [
            'nights' => $this->nights,
            'pricePerNight' => $this->pricePerNight,
            'baseAmount' => $this->baseAmount,
            'cleaningFee' => $this->cleaningFee,
            'serviceFee' => $this->serviceFee,
            'taxesAmount' => $this->taxesAmount,
            'totalAmount' => $this->totalAmount,
            'currency' => $this->currency,
        ];
    }
}
