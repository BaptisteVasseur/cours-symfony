<?php

declare(strict_types=1);

namespace App\Service;

final readonly class BookingAvailabilityResult
{
    /**
     * @param list<string> $reasonCodes
     */
    public function __construct(
        private bool $available,
        private array $reasonCodes = [],
    ) {
    }

    public static function available(): self
    {
        return new self(true);
    }

    /**
     * @param list<string> $reasonCodes
     */
    public static function unavailable(array $reasonCodes): self
    {
        return new self(false, array_values(array_unique($reasonCodes)));
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    /**
     * @return list<string>
     */
    public function getReasonCodes(): array
    {
        return $this->reasonCodes;
    }

    public function getPrimaryReasonCode(): ?string
    {
        return $this->reasonCodes[0] ?? null;
    }

    public function hasReason(string $reasonCode): bool
    {
        return in_array($reasonCode, $this->reasonCodes, true);
    }
}
