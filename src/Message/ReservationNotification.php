<?php

declare(strict_types=1);

namespace App\Message;

final class ReservationNotification
{
    public const string PENDING_REQUEST = 'pending_request';
    public const string CONFIRMED = 'confirmed';
    public const string REFUSED = 'refused';
    public const string CANCELLED = 'cancelled';

    public function __construct(
        public readonly string $reservationId,
        public readonly string $event,
    ) {
    }
}
