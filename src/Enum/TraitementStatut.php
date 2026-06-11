<?php

namespace App\Enum;

enum TraitementStatut: string
{
    case NOUVEAU = 'nouveau';
    case EN_COURS = 'en_cours';
    case TRAITE = 'traite';
    case REJETE = 'rejete';
}
