<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Service\AvailabilityService;
use App\Service\PricingService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PricingAndAvailabilityTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testMinimumStayIsEnforced(): void
    {
        $propertyRepository = static::getContainer()->get(PropertyRepository::class);
        $availabilityService = static::getContainer()->get(AvailabilityService::class);

        $property = $propertyRepository->findOneBy(['title' => 'Maison Test — Vue Mer']);
        self::assertNotNull($property);

        $checkin = new \DateTimeImmutable('+120 days');
        $checkout = $checkin->modify('+1 day');

        $availabilityService->configureDates($property, $checkin, $checkin->modify('+1 day'), null, 3);

        $reason = $availabilityService->getUnavailabilityReason($property, $checkin, $checkout, 2);
        self::assertNotNull($reason);
        self::assertStringContainsString('durée minimale', $reason);
    }

    public function testPriceOverrideIsUsedInCalculation(): void
    {
        $propertyRepository = static::getContainer()->get(PropertyRepository::class);
        $availabilityService = static::getContainer()->get(AvailabilityService::class);
        $pricingService = static::getContainer()->get(PricingService::class);

        $property = $propertyRepository->findOneBy(['title' => 'Maison Test — Vue Mer']);
        self::assertNotNull($property);

        $checkin = new \DateTimeImmutable('+130 days');
        $checkout = $checkin->modify('+2 days');

        $availabilityService->configureDates($property, $checkin, $checkout, '999.00', null);

        $pricing = $pricingService->calculate($property, $checkin, $checkout);
        self::assertSame(1998.0, $pricing['subtotal']);
    }

    public function testCompletePastReservationsCommand(): void
    {
        $reservationRepository = static::getContainer()->get(ReservationRepository::class);
        $completed = $reservationRepository->findOneBy(['status' => 'completed']);
        self::assertNotNull($completed);

        $pastConfirmed = $reservationRepository->findConfirmedPastCheckout();
        foreach ($pastConfirmed as $reservation) {
            self::assertLessThanOrEqual(new \DateTimeImmutable('today'), $reservation->getCheckoutDate());
        }
    }
}
