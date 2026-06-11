<?php

declare(strict_types=1);

namespace App\Service\Availability;

enum UnavailabilityReason: string
{
    case INVALID_DATES = 'invalid_dates';
    case PROPERTY_NOT_FOUND = 'property_not_found';
    case PROPERTY_NOT_PUBLISHED = 'property_not_published';
    case CAPACITY_EXCEEDED = 'capacity_exceeded';
    case BLOCKED_BY_HOST = 'blocked_by_host';
    case ALREADY_BOOKED = 'already_booked';

    public function label(): string
    {
        return match ($this) {
            self::INVALID_DATES => 'Les dates fournies sont invalides.',
            self::PROPERTY_NOT_FOUND => 'Logement introuvable.',
            self::PROPERTY_NOT_PUBLISHED => 'Ce logement n\'est pas publié.',
            self::CAPACITY_EXCEEDED => 'La capacité du logement est insuffisante pour ce nombre de voyageurs.',
            self::BLOCKED_BY_HOST => 'Ces dates sont indisponibles.',
            self::ALREADY_BOOKED => 'Ces dates sont indisponibles.',
        };
    }
}
