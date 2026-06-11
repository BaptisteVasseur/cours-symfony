<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ReservationNotificationMessage
{
    public function __construct(
        public string $reservationId,
        public string $event,
    ) {
    }
}
