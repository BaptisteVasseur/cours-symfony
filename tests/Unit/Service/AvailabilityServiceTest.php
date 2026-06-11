<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Listing;
use App\Repository\AvailabilityBlockRepository;
use App\Repository\BookingRepository;
use App\Service\AvailabilityService;
use App\ValueObject\DateRange;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AvailabilityServiceTest extends TestCase
{
    private BookingRepository&MockObject $bookingRepository;
    private AvailabilityBlockRepository&MockObject $blockRepository;
    private AvailabilityService $service;

    protected function setUp(): void
    {
        $this->bookingRepository = $this->createMock(BookingRepository::class);
        $this->blockRepository = $this->createMock(AvailabilityBlockRepository::class);
        $this->service = new AvailabilityService($this->bookingRepository, $this->blockRepository);
    }

    private function publishedListing(int $maxGuests = 4): Listing
    {
        return (new Listing())->setStatus('published')->setMaxGuests($maxGuests);
    }

    private function range(): DateRange
    {
        return DateRange::fromStrings('2026-07-10', '2026-07-13');
    }

    public function testUnpublishedListingIsNeverAvailable(): void
    {
        $listing = (new Listing())->setStatus('draft')->setMaxGuests(4);

        self::assertFalse($this->service->isAvailable($listing, $this->range(), 2));
    }

    public function testGuestsExceedingCapacityIsNotAvailable(): void
    {
        self::assertFalse($this->service->isAvailable($this->publishedListing(2), $this->range(), 5));
    }

    public function testBlockedDatesAreNotAvailable(): void
    {
        $this->blockRepository->method('hasBlockOverlap')->willReturn(true);

        self::assertFalse($this->service->isAvailable($this->publishedListing(), $this->range(), 2));
    }

    public function testConfirmedOverlapIsNotAvailable(): void
    {
        $this->blockRepository->method('hasBlockOverlap')->willReturn(false);
        $this->bookingRepository->method('hasConfirmedOverlap')->willReturn(true);

        self::assertFalse($this->service->isAvailable($this->publishedListing(), $this->range(), 2));
    }

    public function testAvailableWhenPublishedFreeAndWithinCapacity(): void
    {
        $this->blockRepository->method('hasBlockOverlap')->willReturn(false);
        $this->bookingRepository->method('hasConfirmedOverlap')->willReturn(false);

        self::assertTrue($this->service->isAvailable($this->publishedListing(), $this->range(), 2));
    }
}
