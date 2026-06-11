<?php

namespace App\Enum;

enum LogementCategorie: string
{
    case LOGEMENT_ENTIER = 'logement_entier';
    case CHAMBRE_PRIVEE = 'chambre_privee';
    case CHAMBRE_PARTAGEE = 'chambre_partagee';
}
