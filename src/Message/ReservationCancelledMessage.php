<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ReservationCancelledMessage
{
    public function __construct(public string $reservationId) {}
}
