<?php

namespace App\Enum;

enum DisponibiliteStatut: string
{
    case DISPONIBLE = 'disponible';
    case BLOQUEE = 'bloquee';
    case RESERVEE = 'reservee';
}
