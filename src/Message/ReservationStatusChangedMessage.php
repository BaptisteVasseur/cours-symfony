<?php

declare(strict_types=1);

namespace App\Message;

final class ReservationStatusChangedMessage
{
    public function __construct(
        public readonly string $reservationId,
        public readonly string $newStatus,
    ) {}
}
