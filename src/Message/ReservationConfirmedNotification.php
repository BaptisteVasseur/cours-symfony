<?php

declare(strict_types=1);

namespace App\Message;

/** Réservation confirmée : à notifier au voyageur et à l'hôte. */
final readonly class ReservationConfirmedNotification implements ReservationNotificationInterface
{
    public function __construct(private string $reservationId)
    {
    }

    public function getReservationId(): string
    {
        return $this->reservationId;
    }
}
