<?php

namespace App\Message;

final readonly class BookingConfirmedMessage
{
    public function __construct(public string $bookingId) {}
}
