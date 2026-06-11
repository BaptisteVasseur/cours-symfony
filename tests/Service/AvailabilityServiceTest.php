<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Property;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use App\Service\AvailabilityService;
use PHPUnit\Framework\TestCase;

final class AvailabilityServiceTest extends TestCase
{
    private \DateTimeImmutable $checkin;
    private \DateTimeImmutable $checkout;

    protected function setUp(): void
    {
        $this->checkin = new \DateTimeImmutable('2026-09-10');
        $this->checkout = new \DateTimeImmutable('2026-09-14');
    }

    public function testAvailableWhenPublishedFreeAndWithinCapacity(): void
    {
        $service = $this->service(overlap: false, blocked: false);

        self::assertTrue($service->isAvailable($this->property(), $this->checkin, $this->checkout, 2));
    }

    public function testUnavailableWhenNotPublished(): void
    {
        $service = $this->service(overlap: false, blocked: false);

        self::assertFalse($service->isAvailable($this->property('draft'), $this->checkin, $this->checkout, 2));
    }

    public function testUnavailableWhenCapacityExceeded(): void
    {
        $service = $this->service(overlap: false, blocked: false);

        self::assertFalse($service->isAvailable($this->property(), $this->checkin, $this->checkout, 99));
    }

    public function testUnavailableWhenConfirmedOverlap(): void
    {
        $service = $this->service(overlap: true, blocked: false);

        self::assertFalse($service->isAvailable($this->property(), $this->checkin, $this->checkout, 2));
    }

    public function testUnavailableWhenHostBlockedDay(): void
    {
        $service = $this->service(overlap: false, blocked: true);

        self::assertFalse($service->isAvailable($this->property(), $this->checkin, $this->checkout, 2));
    }

    public function testUnavailableWhenCheckinNotBeforeCheckout(): void
    {
        $service = $this->service(overlap: false, blocked: false);

        self::assertFalse($service->isAvailable($this->property(), $this->checkout, $this->checkin, 2));
    }

    private function service(bool $overlap, bool $blocked): AvailabilityService
    {
        $reservations = $this->createMock(ReservationRepository::class);
        $reservations->method('hasConfirmedOverlap')->willReturn($overlap);

        $availabilities = $this->createMock(PropertyAvailabilityRepository::class);
        $availabilities->method('hasBlockedDay')->willReturn($blocked);

        return new AvailabilityService($reservations, $availabilities);
    }

    private function property(string $status = 'published'): Property
    {
        return (new Property())
            ->setStatus($status)
            ->setMaxGuests(4);
    }
}
