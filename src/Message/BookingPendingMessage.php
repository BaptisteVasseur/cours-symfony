<?php

declare(strict_types=1);

namespace App\Message;

final readonly class BookingPendingMessage
{
    public function __construct(
        public string $reservationId,
    ) {}
}
