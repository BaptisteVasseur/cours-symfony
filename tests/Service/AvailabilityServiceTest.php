<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Property;
use App\Repository\BlockoutRepository;
use App\Repository\ReservationRepository;
use App\Service\AvailabilityService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AvailabilityServiceTest extends TestCase
{
    private BlockoutRepository&MockObject $blockoutRepo;
    private ReservationRepository&MockObject $reservationRepo;
    private AvailabilityService $service;

    protected function setUp(): void
    {
        $this->blockoutRepo = $this->createMock(BlockoutRepository::class);
        $this->reservationRepo = $this->createMock(ReservationRepository::class);

        $this->service = new AvailabilityService(
            $this->blockoutRepo,
            $this->reservationRepo,
        );
    }

    // -------------------------------------------------------------------------
    // Cas nominal — tout est ok
    // -------------------------------------------------------------------------

    public function testIsAvailableReturnsTrueWhenAllConditionsMet(): void
    {
        $property = $this->makeProperty(status: 'published', maxGuests: 4);
        $checkin = new \DateTimeImmutable('2025-08-01');
        $checkout = new \DateTimeImmutable('2025-08-05');

        $this->blockoutRepo
            ->method('hasBlockoutInRange')
            ->willReturn(false);

        $this->reservationRepo
            ->method('hasConfirmedOverlapping')
            ->willReturn(false);

        self::assertTrue($this->service->isAvailable($property, $checkin, $checkout, 2));
    }

    public function testGetUnavailabilityReasonsReturnsEmptyArrayWhenAvailable(): void
    {
        $property = $this->makeProperty(status: 'published', maxGuests: 4);
        $checkin = new \DateTimeImmutable('2025-08-01');
        $checkout = new \DateTimeImmutable('2025-08-05');

        $this->blockoutRepo->method('hasBlockoutInRange')->willReturn(false);
        $this->reservationRepo->method('hasConfirmedOverlapping')->willReturn(false);

        $reasons = $this->service->getUnavailabilityReasons($property, $checkin, $checkout, 2);

        self::assertSame([], $reasons);
    }

    // -------------------------------------------------------------------------
    // Règle 1 — statut du logement
    // -------------------------------------------------------------------------

    #[DataProvider('nonPublishedStatusProvider')]
    public function testIsUnavailableWhenPropertyNotPublished(string $status): void
    {
        $property = $this->makeProperty(status: $status, maxGuests: 4);
        $checkin = new \DateTimeImmutable('2025-08-01');
        $checkout = new \DateTimeImmutable('2025-08-05');

        $this->blockoutRepo->method('hasBlockoutInRange')->willReturn(false);
        $this->reservationRepo->method('hasConfirmedOverlapping')->willReturn(false);

        self::assertFalse($this->service->isAvailable($property, $checkin, $checkout, 2));

        $reasons = $this->service->getUnavailabilityReasons($property, $checkin, $checkout, 2);
        self::assertArrayHasKey('property_not_published', $reasons);
    }

    /** @return array<string, array{string}> */
    public static function nonPublishedStatusProvider(): array
    {
        return [
            'draft'   => ['draft'],
            'pending' => ['pending'],
        ];
    }

    // -------------------------------------------------------------------------
    // Règle 2 — blockout hôte
    // -------------------------------------------------------------------------

    public function testIsUnavailableWhenBlockoutPresent(): void
    {
        $property = $this->makeProperty(status: 'published', maxGuests: 4);
        $checkin = new \DateTimeImmutable('2025-08-01');
        $checkout = new \DateTimeImmutable('2025-08-05');

        $this->blockoutRepo
            ->expects(self::exactly(2))
            ->method('hasBlockoutInRange')
            ->with($property, $checkin, $checkout)
            ->willReturn(true);

        $this->reservationRepo->method('hasConfirmedOverlapping')->willReturn(false);

        self::assertFalse($this->service->isAvailable($property, $checkin, $checkout, 2));

        $reasons = $this->service->getUnavailabilityReasons($property, $checkin, $checkout, 2);
        self::assertArrayHasKey('blockout', $reasons);
    }

    // -------------------------------------------------------------------------
    // Règle 3 — réservation confirmed en conflit
    // -------------------------------------------------------------------------

    public function testIsUnavailableWhenConfirmedReservationOverlaps(): void
    {
        $property = $this->makeProperty(status: 'published', maxGuests: 4);
        $checkin = new \DateTimeImmutable('2025-08-03');
        $checkout = new \DateTimeImmutable('2025-08-07');

        $this->blockoutRepo->method('hasBlockoutInRange')->willReturn(false);

        $this->reservationRepo
            ->expects(self::exactly(2))
            ->method('hasConfirmedOverlapping')
            ->with($property, $checkin, $checkout)
            ->willReturn(true);

        self::assertFalse($this->service->isAvailable($property, $checkin, $checkout, 2));

        $reasons = $this->service->getUnavailabilityReasons($property, $checkin, $checkout, 2);
        self::assertArrayHasKey('confirmed_overlap', $reasons);
    }

    // -------------------------------------------------------------------------
    // Règle 4 — capacité d'accueil
    // -------------------------------------------------------------------------

    public function testIsUnavailableWhenGuestsExceedCapacity(): void
    {
        $property = $this->makeProperty(status: 'published', maxGuests: 2);
        $checkin = new \DateTimeImmutable('2025-08-01');
        $checkout = new \DateTimeImmutable('2025-08-05');

        $this->blockoutRepo->method('hasBlockoutInRange')->willReturn(false);
        $this->reservationRepo->method('hasConfirmedOverlapping')->willReturn(false);

        self::assertFalse($this->service->isAvailable($property, $checkin, $checkout, 5));

        $reasons = $this->service->getUnavailabilityReasons($property, $checkin, $checkout, 5);
        self::assertArrayHasKey('capacity_exceeded', $reasons);
    }

    public function testIsAvailableWhenGuestsEqualCapacity(): void
    {
        $property = $this->makeProperty(status: 'published', maxGuests: 4);
        $checkin = new \DateTimeImmutable('2025-08-01');
        $checkout = new \DateTimeImmutable('2025-08-05');

        $this->blockoutRepo->method('hasBlockoutInRange')->willReturn(false);
        $this->reservationRepo->method('hasConfirmedOverlapping')->willReturn(false);

        self::assertTrue($this->service->isAvailable($property, $checkin, $checkout, 4));
    }

    // -------------------------------------------------------------------------
    // Cumul de raisons
    // -------------------------------------------------------------------------

    public function testGetUnavailabilityReasonsAccumulatesAllFailures(): void
    {
        $property = $this->makeProperty(status: 'draft', maxGuests: 1);
        $checkin = new \DateTimeImmutable('2025-08-01');
        $checkout = new \DateTimeImmutable('2025-08-05');

        $this->blockoutRepo->method('hasBlockoutInRange')->willReturn(true);
        $this->reservationRepo->method('hasConfirmedOverlapping')->willReturn(true);

        $reasons = $this->service->getUnavailabilityReasons($property, $checkin, $checkout, 4);

        self::assertArrayHasKey('property_not_published', $reasons);
        self::assertArrayHasKey('blockout', $reasons);
        self::assertArrayHasKey('confirmed_overlap', $reasons);
        self::assertArrayHasKey('capacity_exceeded', $reasons);
        self::assertCount(4, $reasons);
    }

    // -------------------------------------------------------------------------
    // Validation des arguments
    // -------------------------------------------------------------------------

    public function testThrowsWhenCheckoutBeforeCheckin(): void
    {
        $property = $this->makeProperty(status: 'published', maxGuests: 4);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->isAvailable(
            $property,
            new \DateTimeImmutable('2025-08-05'),
            new \DateTimeImmutable('2025-08-01'),
            2,
        );
    }

    public function testThrowsWhenCheckoutEqualsCheckin(): void
    {
        $property = $this->makeProperty(status: 'published', maxGuests: 4);
        $same = new \DateTimeImmutable('2025-08-01');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->isAvailable($property, $same, $same, 2);
    }

    public function testThrowsWhenGuestsCountIsZero(): void
    {
        $property = $this->makeProperty(status: 'published', maxGuests: 4);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->isAvailable(
            $property,
            new \DateTimeImmutable('2025-08-01'),
            new \DateTimeImmutable('2025-08-05'),
            0,
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeProperty(string $status, int $maxGuests): Property
    {
        $property = $this->createMock(Property::class);
        $property->method('getStatus')->willReturn($status);
        $property->method('getMaxGuests')->willReturn($maxGuests);

        return $property;
    }
}
