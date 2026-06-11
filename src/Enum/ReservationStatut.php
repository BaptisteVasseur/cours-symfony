<?php

namespace App\Enum;

enum ReservationStatut: string
{
    case BROUILLON = 'brouillon';
    case EN_ATTENTE_HOTE = 'en_attente_hote';
    case REFUSEE = 'refusee';
    case ACCEPTEE_EN_ATTENTE_PAIEMENT = 'acceptee_en_attente_paiement';
    case EXPIREE = 'expiree';
    case CONFIRMEE = 'confirmee';
    case ANNULEE_PAR_VOYAGEUR = 'annulee_par_voyageur';
    case ANNULEE_PAR_HOTE = 'annulee_par_hote';
    case TERMINEE = 'terminee';
}
