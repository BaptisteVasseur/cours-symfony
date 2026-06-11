<?php

declare(strict_types=1);

namespace App\Exception;

use App\Enum\BookingStatus;

final class InvalidBookingTransitionException extends \LogicException
{
    public function __construct(BookingStatus $from, BookingStatus $to)
    {
        parent::__construct(sprintf(
            'Transition de réservation interdite : %s → %s.',
            $from->value,
            $to->value,
        ));
    }
}
