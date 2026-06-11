<?php

declare(strict_types=1);

namespace App\Service\Availability;

final readonly class AvailabilityResult
{
    private function __construct(
        public bool $available,
        public ?UnavailabilityReason $reason = null,
    ) {
    }

    public static function ok(): self
    {
        return new self(true);
    }

    public static function ko(UnavailabilityReason $reason): self
    {
        return new self(false, $reason);
    }

    public function reasonLabel(): ?string
    {
        return $this->reason?->label();
    }
}
