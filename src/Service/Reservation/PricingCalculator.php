<?php

declare(strict_types=1);

namespace App\Service\Reservation;

use App\Entity\Property;
use App\Repository\PropertyAvailabilityRepository;

/**
 * Calcule le coût d'un séjour. Source autoritative : utilisé au checkout pour
 * fixer le montant persisté. Le tarif par nuit peut être surchargé par jour via
 * PropertyAvailability.priceOverride (tarification dynamique, §A.1 optionnel) ;
 * les jours sans surcharge retombent sur le tarif de base du logement.
 *
 * Les surcharges sont chargées en UNE requête (findForRangeIndexed) ; la boucle
 * qui somme les nuitées est purement en mémoire, sans requête par jour.
 */
final class PricingCalculator
{
    private const float SERVICE_FEE_RATE = 0.12;

    public function __construct(
        private readonly PropertyAvailabilityRepository $availabilityRepository,
    ) {
    }

    public function calculate(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): PriceBreakdown
    {
        $nights = (int) $checkin->diff($checkout)->days;
        if ($nights < 1) {
            throw new \InvalidArgumentException('La durée du séjour doit être d\'au moins une nuit.');
        }

        $baseRate = (float) $property->getPricePerNight();
        $overrides = $this->availabilityRepository->findForRangeIndexed($property, $checkin, $checkout);

        $subtotal = 0.0;
        $night = $checkin;
        for ($i = 0; $i < $nights; ++$i) {
            $row = $overrides[$night->format('Y-m-d')] ?? null;
            $subtotal += ($row !== null && $row->getPriceOverride() !== null)
                ? (float) $row->getPriceOverride()
                : $baseRate;
            $night = $night->modify('+1 day');
        }

        $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
        $serviceFee = round($subtotal * self::SERVICE_FEE_RATE, 2);
        $total = round($subtotal + $cleaningFee + $serviceFee, 2);

        return new PriceBreakdown(
            nights: $nights,
            baseNightlyRate: $baseRate,
            subtotal: round($subtotal, 2),
            cleaningFee: $cleaningFee,
            serviceFee: $serviceFee,
            total: $total,
        );
    }
}
