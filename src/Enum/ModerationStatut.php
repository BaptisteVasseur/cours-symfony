<?php

namespace App\Enum;

enum ModerationStatut: string
{
    case EN_ATTENTE = 'en_attente';
    case VALIDEE = 'validee';
    case REFUSEE = 'refusee';
}
