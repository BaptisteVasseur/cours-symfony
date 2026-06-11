<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Résultat de l'algorithme de disponibilité (A.2).
 */
enum AvailabilityStatus: string
{
    case Available = 'available';
    case InvalidRange = 'invalid_range';
    case NotPublished = 'not_published';
    case Blocked = 'blocked';
    case Reserved = 'reserved';
    case InsufficientCapacity = 'insufficient_capacity';

    public function isAvailable(): bool
    {
        return $this === self::Available;
    }

    public function message(): string
    {
        return match ($this) {
            self::Available => 'Ces dates sont disponibles.',
            self::InvalidRange => 'La date de départ doit être postérieure à la date d\'arrivée.',
            self::NotPublished => 'Ce logement n\'est pas disponible à la réservation.',
            self::Blocked => 'Une ou plusieurs nuits de cette période ont été bloquées par l\'hôte.',
            self::Reserved => 'Ces dates sont déjà réservées.',
            self::InsufficientCapacity => 'Le nombre de voyageurs dépasse la capacité du logement.',
        };
    }
}
