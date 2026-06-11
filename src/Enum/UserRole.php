<?php

namespace App\Enum;

enum UserRole: string
{
    case VOYAGEUR = 'ROLE_VOYAGEUR';
    case HOTE = 'ROLE_HOTE';
    case ADMIN = 'ROLE_ADMIN';
    case SUPER_ADMIN = 'ROLE_SUPER_ADMIN';
    case SUPPORT = 'ROLE_SUPPORT';
}
