<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Uid\UuidV7;

class ReservationCreatedMessage
{
    private UuidV7 $reservationId;

    public function __construct(UuidV7 $reservationId)
    {
        $this->reservationId = $reservationId;
    }

    public function getReservationId(): UuidV7
    {
        return $this->reservationId;
    }
}