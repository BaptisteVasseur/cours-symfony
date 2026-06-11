<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\Schedule\CompleteBookingsMessage;
use App\Message\Schedule\ExpireBookingsMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('booking')]
final class BookingScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(private readonly CacheInterface $cache) {}

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->with(
                RecurringMessage::every('1 hour', new ExpireBookingsMessage()),
                RecurringMessage::every('1 hour', new CompleteBookingsMessage()),
            )
            ->stateful($this->cache);
    }
}
