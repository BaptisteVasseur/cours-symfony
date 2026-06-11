<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Service\Availability\UnavailabilityReason;

final class BookingUnavailableException extends \RuntimeException
{
    public function __construct(
        public readonly UnavailabilityReason $reason,
    ) {
        parent::__construct($reason->label());
    }
}
