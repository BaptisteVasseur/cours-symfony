<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Détail chiffré d'un séjour. Montants en chaînes décimales (compatibles Doctrine DECIMAL).
 */
final readonly class PriceBreakdown
{
    public function __construct(
        public int $nights,
        public string $nightsTotal,
        public string $cleaningFee,
        public string $serviceFee,
        public string $securityDeposit,
        public string $total,
        public string $currency,
    ) {
    }
}
