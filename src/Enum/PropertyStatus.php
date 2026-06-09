<?php

namespace App\Enum;

enum PropertyStatus: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case SUSPENDED = 'SUSPENDED';
}
