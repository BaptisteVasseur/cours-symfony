<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\PriceQuote;
use App\Entity\Listing;
use App\ValueObject\DateRange;

final class PricingService
{
    public function quote(Listing $listing, DateRange $range): PriceQuote
    {
        $nights = $range->nights();

        $priceCents = $this->toCents($listing->getPricePerNight());
        $baseCents = $priceCents * $nights;
        $cleaningCents = $this->toCents($listing->getCleaningFee());
        $serviceCents = $this->toCents($listing->getServiceFee());
        $taxesCents = 0;

        $totalCents = $baseCents + $cleaningCents + $serviceCents + $taxesCents;

        return new PriceQuote(
            nights: $nights,
            pricePerNight: $this->toDecimal($priceCents),
            baseAmount: $this->toDecimal($baseCents),
            cleaningFee: $this->toDecimal($cleaningCents),
            serviceFee: $this->toDecimal($serviceCents),
            taxesAmount: $this->toDecimal($taxesCents),
            totalAmount: $this->toDecimal($totalCents),
            currency: $listing->getCurrency() ?? 'EUR',
        );
    }

    private function toCents(?string $decimal): int
    {
        if ($decimal === null || $decimal === '') {
            return 0;
        }

        return (int) round((float) $decimal * 100);
    }

    private function toDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
