<?php

declare(strict_types=1);

namespace App\Message;

final class BookingConfirmedMessage
{
    public function __construct(
        public readonly string $reservationId,
        public readonly string $propertyTitle,
        public readonly string $guestFirstName,
        public readonly string $guestEmail,
        public readonly string $hostFirstName,
        public readonly string $hostEmail,
        public readonly string $checkinDate,
        public readonly string $checkoutDate,
        public readonly float  $totalPrice,
        public readonly string $currency,
        public readonly string $bookingUrl,
    ) {}
}
