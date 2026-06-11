<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Listing;
use App\Service\PricingService;
use App\ValueObject\DateRange;
use PHPUnit\Framework\TestCase;

final class PricingServiceTest extends TestCase
{
    public function testQuoteComputesNightsTimesPricePlusFees(): void
    {
        $listing = (new Listing())
            ->setPricePerNight('95.00')
            ->setCleaningFee('20.00')
            ->setServiceFee('15.00')
            ->setCurrency('EUR');

        $quote = (new PricingService())->quote($listing, DateRange::fromStrings('2026-07-10', '2026-07-13'));

        self::assertSame(3, $quote->nights);
        self::assertSame('285.00', $quote->baseAmount);   // 3 × 95
        self::assertSame('20.00', $quote->cleaningFee);
        self::assertSame('15.00', $quote->serviceFee);
        self::assertSame('320.00', $quote->totalAmount);  // 285 + 20 + 15
        self::assertSame('EUR', $quote->currency);
    }

    public function testQuoteHandlesNullFees(): void
    {
        $listing = (new Listing())->setPricePerNight('100.00');

        $quote = (new PricingService())->quote($listing, DateRange::fromStrings('2026-07-10', '2026-07-12'));

        self::assertSame('200.00', $quote->baseAmount);
        self::assertSame('0.00', $quote->cleaningFee);
        self::assertSame('200.00', $quote->totalAmount);
    }
}
