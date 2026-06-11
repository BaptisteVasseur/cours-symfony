<?php

declare(strict_types=1);

namespace App\Message;

final readonly class BookingCreatedMessage
{
    public function __construct(
        public string $reservationId,
    ) {
    }
}
