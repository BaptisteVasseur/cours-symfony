<?php

namespace App\Enum;

enum CmsStatut: string
{
    case BROUILLON = 'brouillon';
    case PUBLIE = 'publie';
    case ARCHIVE = 'archive';
}
