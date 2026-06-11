<?php

namespace App\Enum;

enum NotificationStatut: string
{
    case NON_LUE = 'non_lue';
    case LUE = 'lue';
    case ENVOYEE = 'envoyee';
    case ECHOUEE = 'echouee';
}
