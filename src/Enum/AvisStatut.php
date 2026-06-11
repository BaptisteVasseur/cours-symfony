<?php

namespace App\Enum;

enum AvisStatut: string
{
    case EN_ATTENTE = 'en_attente';
    case PUBLIE = 'publie';
    case MASQUE = 'masque';
    case SUPPRIME = 'supprime';
}
