<?php

declare(strict_types=1);

namespace App\Message;

final class ReservationCancelledMessage
{
    public function __construct(
        public readonly string $reservationId,
        public readonly string $cancelledBy,
        public readonly string $reason,
    ) {}
}
