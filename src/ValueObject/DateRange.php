<?php

declare(strict_types=1);

namespace App\ValueObject;

final class DateRange
{
    public readonly \DateTimeImmutable $checkIn;
    public readonly \DateTimeImmutable $checkOut;

    public function __construct(\DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut)
    {
        $checkIn = $checkIn->setTime(0, 0);
        $checkOut = $checkOut->setTime(0, 0);

        if ($checkOut <= $checkIn) {
            throw new \InvalidArgumentException('La date de départ doit être postérieure à la date d\'arrivée.');
        }

        $this->checkIn = $checkIn;
        $this->checkOut = $checkOut;
    }

    public static function fromStrings(string $checkIn, string $checkOut): self
    {
        return new self(new \DateTimeImmutable($checkIn), new \DateTimeImmutable($checkOut));
    }
    public function nights(): int
    {
        return (int) $this->checkIn->diff($this->checkOut)->days;
    }
    public function overlaps(self $other): bool
    {
        return $this->checkIn < $other->checkOut && $other->checkIn < $this->checkOut;
    }

    public function isInPast(?\DateTimeImmutable $now = null): bool
    {
        $today = ($now ?? new \DateTimeImmutable())->setTime(0, 0);

        return $this->checkIn < $today;
    }
}
