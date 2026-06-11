<?php

declare(strict_types=1);

namespace App\Service\Availability;

/**
 * Résultat d'un contrôle de disponibilité : disponible ou non, et si non, la
 * liste des motifs (pour affichage utilisateur / logs). Immuable.
 */
final readonly class AvailabilityResult
{
    /**
     * @param list<string> $reasons
     */
    private function __construct(
        public bool $available,
        public array $reasons = [],
    ) {
    }

    public static function available(): self
    {
        return new self(true);
    }

    /**
     * @param list<string> $reasons
     */
    public static function unavailable(array $reasons): self
    {
        return new self(false, $reasons);
    }

    public function firstReason(): ?string
    {
        return $this->reasons[0] ?? null;
    }
}
