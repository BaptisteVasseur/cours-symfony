<?php

declare(strict_types=1);

namespace App\Message;

final readonly class BookingCancelledMessage
{
    public function __construct(
        public string $reservationId,
        public string $cancelledByUserId,
        public string $reason,
    ) {}
}
