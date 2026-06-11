<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Booking;
use App\Enum\BookingStatus;
use App\Exception\InvalidBookingTransitionException;
use App\Service\BookingStateMachineService;
use PHPUnit\Framework\TestCase;

final class BookingStateMachineServiceTest extends TestCase
{
    private BookingStateMachineService $sm;

    protected function setUp(): void
    {
        $this->sm = new BookingStateMachineService();
    }

    public function testAllowedTransitions(): void
    {
        self::assertTrue($this->sm->can(BookingStatus::Pending, BookingStatus::Confirmed));
        self::assertTrue($this->sm->can(BookingStatus::Pending, BookingStatus::Cancelled));
        self::assertTrue($this->sm->can(BookingStatus::Confirmed, BookingStatus::Completed));
        self::assertTrue($this->sm->can(BookingStatus::Confirmed, BookingStatus::Cancelled));
    }

    public function testForbiddenTransitions(): void
    {
        self::assertFalse($this->sm->can(BookingStatus::Cancelled, BookingStatus::Confirmed));
        self::assertFalse($this->sm->can(BookingStatus::Completed, BookingStatus::Cancelled));
        self::assertFalse($this->sm->can(BookingStatus::Pending, BookingStatus::Completed));
    }

    public function testApplyChangesStatus(): void
    {
        $booking = (new Booking())->setBookingStatus(BookingStatus::Pending);

        $this->sm->apply($booking, BookingStatus::Confirmed);

        self::assertSame(BookingStatus::Confirmed, $booking->getBookingStatus());
    }

    public function testApplyRejectsInvalidTransition(): void
    {
        $booking = (new Booking())->setBookingStatus(BookingStatus::Cancelled);

        $this->expectException(InvalidBookingTransitionException::class);

        $this->sm->apply($booking, BookingStatus::Confirmed);
    }
}
