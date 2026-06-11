<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ReservationConfirmedMessage
{
    public function __construct(
        private string $reservationId,
    ) {
    }

    public function getReservationId(): string
    {
        return $this->reservationId;
    }
}
