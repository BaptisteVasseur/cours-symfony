<?php

declare(strict_types=1);

namespace App\Enum;

enum ReservationNotificationType: string
{
    case CREATED_PENDING = 'created_pending';
    case CREATED_CONFIRMED = 'created_confirmed';
    case ACCEPTED = 'accepted';
    case REFUSED = 'refused';
    case CANCELLED = 'cancelled';
}
