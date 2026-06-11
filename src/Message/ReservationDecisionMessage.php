<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ReservationDecisionMessage
{
    public function __construct(
        public string $reservationId,
        public string $decision,
        public ?string $reason,
    ) {}
}
