<?php

declare(strict_types=1);

namespace App\Message;

/** Demande refusée par l'hôte : à notifier au voyageur (motif inclus). */
final readonly class ReservationRejectedNotification implements ReservationNotificationInterface
{
    public function __construct(private string $reservationId)
    {
    }

    public function getReservationId(): string
    {
        return $this->reservationId;
    }
}
