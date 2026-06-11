<?php

namespace App\Enum;

enum MessageStatut: string
{
    case ENVOYE = 'envoye';
    case LU = 'lu';
    case SUPPRIME = 'supprime';
    case MODERE = 'modere';
}
