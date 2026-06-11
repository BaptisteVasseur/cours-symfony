<?php

namespace App\Dto;

final readonly class BookingRequestDto
{
    public function __construct(
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $guestsCount,
    ) {}

    public function getNights(): int
    {
        return $this->checkIn->diff($this->checkOut)->days;
    }

    public function computeTotal(string $pricePerNight): float
    {
        return $this->getNights() * (float) $pricePerNight;
    }

    public static function fromRawValues(string $checkInRaw, string $checkOutRaw, int $guests): ?self
    {
        $checkIn  = \DateTimeImmutable::createFromFormat('Y-m-d', $checkInRaw) ?: null;
        $checkOut = \DateTimeImmutable::createFromFormat('Y-m-d', $checkOutRaw) ?: null;

        if (!$checkIn || !$checkOut || $checkOut <= $checkIn || $guests < 1) {
            return null;
        }

        return new self($checkIn, $checkOut, $guests);
    }
}
