<?php

declare(strict_types=1);

namespace App\Service\Availability;

final readonly class AvailabilityResult
{
    private function __construct(
        public bool $available,
        public ?AvailabilityFailureReason $reason,
    ) {
    }

    public static function available(): self
    {
        return new self(true, null);
    }

    public static function unavailable(AvailabilityFailureReason $reason): self
    {
        return new self(false, $reason);
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function getReason(): ?AvailabilityFailureReason
    {
        return $this->reason;
    }
}
