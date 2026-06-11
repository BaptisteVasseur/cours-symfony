<?php

declare(strict_types=1);

namespace App\Message;

final class BookingRequestedNotification
{
    public function __construct(
        public readonly string $reservationId,
    ) {
    }
}
