<?php

namespace App\Enum;

enum DocumentStatut: string
{
    case EN_ATTENTE = 'en_attente';
    case VALIDE = 'valide';
    case REFUSE = 'refuse';
    case EXPIRE = 'expire';
}
