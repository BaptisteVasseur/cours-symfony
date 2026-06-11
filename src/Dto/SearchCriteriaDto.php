<?php

namespace App\Dto;

final readonly class SearchCriteriaDto
{
    public function __construct(
        public ?string $destination,
        public ?\DateTimeImmutable $checkIn,
        public ?\DateTimeImmutable $checkOut,
        public ?int $guests,
    ) {}

    public function hasDateRange(): bool
    {
        return $this->checkIn !== null && $this->checkOut !== null;
    }

    public function getNights(): ?int
    {
        return $this->hasDateRange() ? $this->checkIn->diff($this->checkOut)->days : null;
    }
}
