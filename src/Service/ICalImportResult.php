<?php

declare(strict_types=1);

namespace App\Service;

final readonly class ICalImportResult
{
    public function __construct(
        public int $events,
        public int $created,
        public int $removed,
        public int $skipped,
    ) {
    }
}
