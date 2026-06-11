<?php

declare(strict_types=1);

namespace App\Message;

final class BookingConfirmedMessage
{
    public function __construct(
        public readonly string $reservationId,
    ) {}
}
