<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\ReservationNotificationType;

final class SendReservationNotification
{
    public function __construct(
        public readonly string $reservationId,
        public readonly ReservationNotificationType $type,
    ) {
    }
}
