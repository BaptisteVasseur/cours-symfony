<?php

namespace App\Message;

final class BookingCancelledMessage
{
    public function __construct(
        public readonly int $bookingId,
        public readonly string $reason,
        public readonly string $cancelledBy, // 'guest' | 'host'
    ) {}
}
