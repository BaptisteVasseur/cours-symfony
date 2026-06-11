<?php

declare(strict_types=1);

namespace App\Enum;

enum BlockReason: string
{
    case HOST = 'host';
    case ICAL_IMPORT = 'ical_import';
}
