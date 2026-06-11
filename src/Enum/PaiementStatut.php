<?php

namespace App\Enum;

enum PaiementStatut: string
{
    case EN_ATTENTE = 'en_attente';
    case AUTORISE = 'autorise';
    case PAYE = 'paye';
    case ECHOUE = 'echoue';
    case REMBOURSE = 'rembourse';
    case PARTIELLEMENT_REMBOURSE = 'partiellement_rembourse';
}
