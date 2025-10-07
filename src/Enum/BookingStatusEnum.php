<?php

declare(strict_types=1);

namespace App\Enum;

enum BookingStatusEnum: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';
}
