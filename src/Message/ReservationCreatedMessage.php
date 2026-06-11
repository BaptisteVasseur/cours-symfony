<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ReservationCreatedMessage
{
    public function __construct(
        public string $reservationId,
    ) {}
}
