<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Résultat d'un contrôle de disponibilité : disponible ou non, avec les motifs d'indisponibilité.
 */
final readonly class AvailabilityResult
{
    /**
     * @param string[] $reasons
     */
    public function __construct(
        public bool $available,
        public array $reasons = [],
    ) {
    }

    public static function available(): self
    {
        return new self(true);
    }

    /**
     * @param string[] $reasons
     */
    public static function unavailable(array $reasons): self
    {
        return new self(false, $reasons);
    }
}
