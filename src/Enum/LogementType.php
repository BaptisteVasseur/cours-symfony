<?php

namespace App\Enum;

enum LogementType: string
{
    case APPARTEMENT = 'appartement';
    case MAISON = 'maison';
    case CHAMBRE_PRIVEE = 'chambre_privee';
    case CHAMBRE_PARTAGEE = 'chambre_partagee';
    case VILLA = 'villa';
    case STUDIO = 'studio';
    case AUTRE = 'autre';
}
