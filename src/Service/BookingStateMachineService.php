<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Enum\BookingStatus;
use App\Exception\InvalidBookingTransitionException;

final class BookingStateMachineService
{
    private const TRANSITIONS = [
        BookingStatus::Pending->value => [BookingStatus::Confirmed, BookingStatus::Cancelled],
        BookingStatus::Confirmed->value => [BookingStatus::Completed, BookingStatus::Cancelled],
        BookingStatus::Cancelled->value => [],
        BookingStatus::Completed->value => [],
    ];

    public function can(BookingStatus $from, BookingStatus $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from->value] ?? [], true);
    }


    public function apply(Booking $booking, BookingStatus $to): void
    {
        $from = $booking->getBookingStatus();

        if (!$this->can($from, $to)) {
            throw new InvalidBookingTransitionException($from, $to);
        }

        $booking->setBookingStatus($to);
    }
}
