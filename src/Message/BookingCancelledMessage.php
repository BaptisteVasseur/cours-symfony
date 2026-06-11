<?php

namespace App\Message;

final readonly class BookingCancelledMessage
{
    public function __construct(public string $bookingId) {}
}
