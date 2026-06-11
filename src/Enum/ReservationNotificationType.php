<?php

declare(strict_types=1);

namespace App\Enum;

enum ReservationNotificationType: string
{
    case PendingRequestToHost = 'pending_request_to_host';
    case ConfirmedToGuest = 'confirmed_to_guest';
    case ConfirmedToHost = 'confirmed_to_host';
    case RejectedToGuest = 'rejected_to_guest';
    case CancelledToGuest = 'cancelled_to_guest';
    case CancelledToHost = 'cancelled_to_host';
}
