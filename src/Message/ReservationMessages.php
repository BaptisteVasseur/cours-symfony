<?php

declare(strict_types=1);

namespace App\Message;

use App\Entity\Reservation;

final class ReservationConfirmedMessage
{
    public function __construct(private readonly string $reservationId)
    {
    }

    public function getReservationId(): string
    {
        return $this->reservationId;
    }
}

final class ReservationRejectedMessage
{
    public function __construct(
        private readonly string $reservationId,
        private readonly string $reason,
    ) {
    }

    public function getReservationId(): string
    {
        return $this->reservationId;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}

final class ReservationCancelledMessage
{
    public function __construct(
        private readonly string $reservationId,
        private readonly string $reason,
    ) {
    }

    public function getReservationId(): string
    {
        return $this->reservationId;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}

final class ReservationCreatedMessage
{
    public function __construct(
        private readonly string $reservationId,
        private readonly bool $isPending,
    ) {
    }

    public function getReservationId(): string
    {
        return $this->reservationId;
    }

    public function isPending(): bool
    {
        return $this->isPending;
    }
}
