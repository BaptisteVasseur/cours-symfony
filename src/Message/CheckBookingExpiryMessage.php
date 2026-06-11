<?php

namespace App\Message;

final readonly class CheckBookingExpiryMessage
{
    public function __construct(public int $bookingId) {}
}
