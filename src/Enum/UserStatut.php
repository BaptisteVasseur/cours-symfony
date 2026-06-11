<?php

namespace App\Enum;

enum UserStatut: string
{
    case ACTIF = 'actif';
    case EN_ATTENTE_VERIFICATION = 'en_attente_verification';
    case SUSPENDU = 'suspendu';
    case SUPPRIME = 'supprime';
}
