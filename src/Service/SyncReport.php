<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Résultat d'une synchronisation iCal d'import (Partie F).
 */
final readonly class SyncReport
{
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $removed = 0,
        public int $conflicts = 0,
    ) {
    }
}
