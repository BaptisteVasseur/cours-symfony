<?php

declare(strict_types=1);

namespace App\Message;

/** Réservation annulée : à notifier au voyageur et à l'hôte (motif inclus). */
final readonly class ReservationCancelledNotification implements ReservationNotificationInterface
{
    public function __construct(private string $reservationId)
    {
    }

    public function getReservationId(): string
    {
        return $this->reservationId;
    }
}
