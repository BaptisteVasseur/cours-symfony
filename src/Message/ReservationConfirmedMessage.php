<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Dispatched when a reservation is confirmed (instant booking or host approval).
 * The handler sends confirmation emails to both the guest and the host.
 */
final readonly class ReservationConfirmedMessage
{
    public function __construct(
        public string $reservationId,
    ) {}
}
