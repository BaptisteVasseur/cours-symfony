<?php

namespace App\Enum;

enum UserRole: string
{
    case TRAVELER = 'TRAVELER';
    case HOST = 'HOST';
    case ADMIN = 'ADMIN';
}
