<?php

declare(strict_types=1);

namespace App\Service\Reservation;

/**
 * Détail chiffré d'un séjour, source unique de vérité pour le montant facturé.
 */
final readonly class PriceBreakdown
{
    public function __construct(
        public int    $nights,
        public float  $baseNightlyRate,
        public float  $subtotal,
        public float  $cleaningFee,
        public float  $serviceFee,
        public float  $total,
        public string $currency = 'EUR',
    )
    {
    }
}
