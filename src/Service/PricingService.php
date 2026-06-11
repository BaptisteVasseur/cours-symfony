<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\PropertyAvailabilityRepository;

/**
 * Calcul du tarif d'un séjour : somme des nuits (avec tarifs journaliers spécifiques),
 * frais de ménage, commission de service et caution.
 *
 * Les montants sont manipulés en CENTIMES (entiers) pour éviter les erreurs de
 * virgule flottante, puis reformatés en chaîne décimale "0.00" en sortie.
 */
final readonly class PricingService
{
    private const SERVICE_RATE = 0.12;
    private const DEFAULT_CURRENCY = 'EUR';

    public function __construct(
        private PropertyAvailabilityRepository $availabilityRepository,
    ) {
    }

    public function compute(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): PriceBreakdown {
        $checkin = $checkin->setTime(0, 0, 0);
        $checkout = $checkout->setTime(0, 0, 0);

        $nights = (int) $checkin->diff($checkout)->days;
        if ($nights < 1) {
            throw new \InvalidArgumentException('La plage de dates doit couvrir au moins une nuit.');
        }

        $baseCents = $this->toCents($property->getPricePerNight());
        $overrides = $this->availabilityRepository->findInRangeIndexedByDate(
            $property,
            $checkin,
            $checkout->modify('-1 day'),
        );

        $nightsTotal = 0;
        $cursor = $checkin;
        for ($i = 0; $i < $nights; ++$i) {
            $key = $cursor->format('Y-m-d');
            $override = isset($overrides[$key]) ? $overrides[$key]->getPriceOverride() : null;
            $nightsTotal += $override !== null ? $this->toCents($override) : $baseCents;
            $cursor = $cursor->modify('+1 day');
        }

        $cleaningFee = $this->toCents($property->getCleaningFee());
        $securityDeposit = $this->toCents($property->getSecurityDeposit());
        $serviceFee = (int) round($nightsTotal * self::SERVICE_RATE);
        $total = $nightsTotal + $cleaningFee + $serviceFee;

        return new PriceBreakdown(
            nights: $nights,
            nightsTotal: $this->fromCents($nightsTotal),
            cleaningFee: $this->fromCents($cleaningFee),
            serviceFee: $this->fromCents($serviceFee),
            securityDeposit: $this->fromCents($securityDeposit),
            total: $this->fromCents($total),
            currency: self::DEFAULT_CURRENCY,
        );
    }

    private function toCents(?string $amount): int
    {
        return (int) round(((float) ($amount ?? '0')) * 100);
    }

    private function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
