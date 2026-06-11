<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Dispatched when a new reservation with status 'pending' is created.
 * The handler notifies the property host that a new booking request needs review.
 */
final readonly class ReservationPendingMessage
{
    public function __construct(
        public string $reservationId,
    ) {}
}
