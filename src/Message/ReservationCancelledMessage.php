<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ReservationCancelledMessage
{
    public function __construct(
        private string $reservationId,
        private ?string $reason = null,
    ) {
    }

    public function getReservationId(): string
    {
        return $this->reservationId;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
