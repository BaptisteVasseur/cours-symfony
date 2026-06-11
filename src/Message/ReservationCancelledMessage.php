<?php

declare(strict_types=1);

namespace App\Message;

final class ReservationCancelledMessage
{
    /**
     * @param string $cancelledBy 'guest' ou 'host'
     */
    public function __construct(
        public readonly string $reservationId,
        public readonly string $cancelledBy,
    ) {}
}
