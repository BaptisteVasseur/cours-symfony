<?php

declare(strict_types=1);

namespace App\Message;

final class ReservationCancelledMessage
{
    public function __construct(public readonly string $reservationId) {}
}
