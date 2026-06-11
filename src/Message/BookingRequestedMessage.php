<?php

namespace App\Message;

final readonly class BookingRequestedMessage
{
    public function __construct(public string $bookingId) {}
}
