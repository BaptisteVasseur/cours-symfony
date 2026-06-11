<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ReservationEmailNotificationMessage
{
    public function __construct(
        public string $reservationId,
        public string $type,
        public ?string $reason = null,
    ) {
    }
}
