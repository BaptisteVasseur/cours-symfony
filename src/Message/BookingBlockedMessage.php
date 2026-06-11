<?php

declare(strict_types=1);

namespace App\Message;

final class BookingBlockedMessage
{
    public function __construct(
        public readonly string $reservationId,
        public readonly string $propertyTitle,
        public readonly string $guestFirstName,
        public readonly string $guestEmail,
        public readonly string $blockedFrom,
        public readonly string $blockedTo,
    ) {}
}
