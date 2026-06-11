<?php

declare(strict_types=1);

namespace App\Message;

final class ReservationNotificationMessage
{
    public function __construct(
        public readonly string $reservationId,
        public readonly string $type, // 'pending' | 'confirmed' | 'cancelled'
        public readonly ?string $cancellationReason = null,
    ) {}
}
