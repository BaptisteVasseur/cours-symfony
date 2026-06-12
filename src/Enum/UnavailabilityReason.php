<?php

declare(strict_types=1);

namespace App\Enum;

enum UnavailabilityReason: string
{
    case TRAVAUX = 'travaux';
    case USAGE_PERSONNEL = 'usage_personnel';
    case AUTRE = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::TRAVAUX => 'Travaux',
            self::USAGE_PERSONNEL => 'Usage personnel',
            self::AUTRE => 'Autre',
        };
    }
}
