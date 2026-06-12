<?php

declare(strict_types=1);

namespace App\Service\Availability;

enum AvailabilityFailureReason: string
{
    case NON_PUBLIE = 'NON_PUBLIE';
    case JOURS_BLOQUES = 'JOURS_BLOQUES';
    case CHEVAUCHEMENT = 'CHEVAUCHEMENT';
    case CAPACITE_INSUFFISANTE = 'CAPACITE_INSUFFISANTE';

    public function message(): string
    {
        return match ($this) {
            self::NON_PUBLIE => 'Ce logement n\'est pas disponible à la réservation.',
            self::JOURS_BLOQUES => 'Certaines dates de la période sont bloquées par l\'hôte.',
            self::CHEVAUCHEMENT => 'Ces dates sont déjà réservées.',
            self::CAPACITE_INSUFFISANTE => 'Le nombre de voyageurs dépasse la capacité du logement.',
        };
    }
}
