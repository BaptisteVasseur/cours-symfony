<?php

namespace App\Message;

final class BookingConfirmedMessage
{
    public function __construct(public readonly int $bookingId) {}
}
