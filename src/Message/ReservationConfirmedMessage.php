<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ReservationConfirmedMessage
{
    public function __construct(public string $reservationId) {}
}
