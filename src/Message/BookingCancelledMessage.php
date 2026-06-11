<?php

declare(strict_types=1);

namespace App\Message;

final class BookingCancelledMessage
{
    public function __construct(
        public readonly string  $reservationId,
        public readonly string  $propertyTitle,
        public readonly string  $guestFirstName,
        public readonly string  $guestEmail,
        public readonly string  $hostEmail,
        public readonly string  $checkinDate,
        public readonly string  $checkoutDate,
        public readonly ?string $cancellationReason,
        public readonly string  $cancelledBy,
    ) {}
}
