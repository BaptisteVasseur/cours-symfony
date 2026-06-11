<?php

namespace App\Enum;

enum LitigeStatut: string
{
    case OUVERT = 'ouvert';
    case EN_COURS = 'en_cours';
    case EN_ATTENTE_REPONSE = 'en_attente_reponse';
    case RESOLU = 'resolu';
    case FERME = 'ferme';
}
