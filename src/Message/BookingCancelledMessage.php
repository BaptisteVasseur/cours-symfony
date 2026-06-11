<?php

declare(strict_types=1);

namespace App\Message;

final class BookingCancelledMessage
{
    public function __construct(
        public readonly string $reservationId,
        public readonly string $reason,
        public readonly string $cancelledBy,
    ) {}
}
