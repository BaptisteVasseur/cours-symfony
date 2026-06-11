<?php

declare(strict_types=1);

namespace App\Message;

final readonly class CheckinReminderMessage
{
    public function __construct(
        public string $reservationId,
    ) {}
}
