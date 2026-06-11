<?php

declare(strict_types=1);

namespace App\Message;

/** Demande en attente : à notifier à l'hôte. */
final readonly class ReservationRequestedNotification implements ReservationNotificationInterface
{
    public function __construct(private string $reservationId)
    {
    }

    public function getReservationId(): string
    {
        return $this->reservationId;
    }
}
