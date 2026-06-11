<?php

namespace App\Enum;

enum LogementStatut: string
{
    case BROUILLON = 'brouillon';
    case EN_ATTENTE = 'en_attente';
    case PUBLIE = 'publie';
    case SUSPENDU = 'suspendu';
    case ARCHIVE = 'archive';
}
