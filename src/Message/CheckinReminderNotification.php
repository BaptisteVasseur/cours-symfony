<?php

declare(strict_types=1);

namespace App\Message;

/** Rappel d'arrivée à J-1 : à notifier au voyageur (G.2). */
final readonly class CheckinReminderNotification implements ReservationNotificationInterface
{
    public function __construct(private string $reservationId)
    {
    }

    public function getReservationId(): string
    {
        return $this->reservationId;
    }
}
