<?php

namespace App\Enum;

enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case PAID = 'PAID';
    case FAILED = 'FAILED';
    case REFUNDED = 'REFUNDED';
}
