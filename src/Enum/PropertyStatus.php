<?php

namespace App\Enum;

enum PropertyStatus: string
{
    case DRAFT     = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case SUSPENDED = 'SUSPENDED';

    public function label(): string
    {
        return match($this) {
            self::DRAFT     => 'Brouillon',
            self::PUBLISHED => 'Publié',
            self::SUSPENDED => 'Suspendu',
        };
    }
}
