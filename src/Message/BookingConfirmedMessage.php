<?php

declare(strict_types=1);

namespace App\Message;

final readonly class BookingConfirmedMessage
{
    public function __construct(public string $reservationId)
    {
    }
}
