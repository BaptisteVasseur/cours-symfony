<?php

declare(strict_types=1);

namespace App\Message;

final class BookingRejectedMessage
{
    public function __construct(public readonly string $bookingId)
    {
    }
}
