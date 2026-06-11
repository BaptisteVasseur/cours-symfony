<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ReservationNotificationMessage
{
    public function __construct(
        private string $reservationId,
        private string $event,
    ) {
    }

    public function getReservationId(): string
    {
        return $this->reservationId;
    }

    public function getEvent(): string
    {
        return $this->event;
    }
}
