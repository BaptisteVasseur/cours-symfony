<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Dispatched when a reservation is cancelled (by guest or host).
 * The handler notifies both parties with the cancellation reason.
 */
final readonly class ReservationCancelledMessage
{
    public function __construct(
        public string $reservationId,
        public string $reason,
    ) {}
}
