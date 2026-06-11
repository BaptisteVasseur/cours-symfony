<?php

declare(strict_types=1);

namespace App\Dto;

final class ICalEvent
{
    public function __construct(
        public readonly ?string $uid,
        public readonly \DateTimeImmutable $start,
        public readonly \DateTimeImmutable $end,
        public readonly ?string $summary,
    ) {
    }
}
