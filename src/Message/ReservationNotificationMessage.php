<?php

declare(strict_types=1);

namespace App\Message;

final class ReservationNotificationMessage
{
    public const TYPE_NEW = 'new';
    public const TYPE_CONFIRMED = 'confirmed';
    public const TYPE_CANCELLED = 'cancelled';

    public function __construct(
        public readonly string $reservationId,
        public readonly string $type,
    ) {}
}
