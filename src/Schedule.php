<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    private const string TIMEZONE = 'Europe/Paris';

    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->add(
                RecurringMessage::every('15 minutes', new RunCommandMessage('app:booking:expire'))
            )
            ->add(
                RecurringMessage::cron(
                    '0 8 * * *',
                    new RunCommandMessage('app:booking:send-reminders'),
                    self::TIMEZONE,
                )
            )
            ->add(
                RecurringMessage::cron(
                    '5 0 * * *',
                    new RunCommandMessage('app:booking:complete'),
                    self::TIMEZONE,
                )
            )
        ;
    }
}
