<?php

namespace App\Message;

final class BookingPendingMessage
{
    public function __construct(public readonly int $bookingId) {}
}
