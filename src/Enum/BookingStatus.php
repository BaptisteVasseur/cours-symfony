<?php

namespace App\Enum;

enum BookingStatus: string
{
    case PENDING = 'PENDING';
    case CONFIRMED = 'CONFIRMED';
    case CANCELLED = 'CANCELLED';
    case COMPLETED = 'COMPLETED';
}
