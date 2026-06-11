<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Booking;
use App\Entity\Listing;
use App\Entity\User;
use App\Enum\BookingStatus;
use App\Exception\UnavailableDatesException;
use App\Message\BookingCancelledMessage;
use App\Message\BookingConfirmedMessage;
use App\Message\BookingCreatedMessage;
use App\Repository\AvailabilityBlockRepository;
use App\Repository\BookingRepository;
use App\Repository\ListingRepository;
use App\Repository\NotificationRepository;
use App\Service\AvailabilityService;
use App\Service\BookingService;
use App\Service\BookingStateMachineService;
use App\Service\NotificationService;
use App\Service\PricingService;
use App\ValueObject\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class BookingServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private ListingRepository&MockObject $listingRepository;
    private BookingRepository&MockObject $bookingRepository;
    private AvailabilityBlockRepository&MockObject $blockRepository;
    private MessageBusInterface&MockObject $bus;
    private BookingService $service;
    private array $dispatched = [];

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->listingRepository = $this->createMock(ListingRepository::class);
        $this->bookingRepository = $this->createMock(BookingRepository::class);
        $this->blockRepository = $this->createMock(AvailabilityBlockRepository::class);
        $this->bus = $this->createMock(MessageBusInterface::class);

        $this->em->method('wrapInTransaction')->willReturnCallback(fn(\Closure $cb) => $cb());

        $this->dispatched = [];
        $this->bus->method('dispatch')->willReturnCallback(function (object $message): Envelope {
            $this->dispatched[] = $message;

            return new Envelope($message);
        });

        $availability = new AvailabilityService($this->bookingRepository, $this->blockRepository);
        $notifications = new NotificationService($this->em, $this->createMock(NotificationRepository::class));

        $this->service = new BookingService(
            $this->em,
            $this->listingRepository,
            $availability,
            new PricingService(),
            new BookingStateMachineService(),
            $notifications,
            $this->bus,
            $this->createMock(LoggerInterface::class),
        );
    }

    private function listing(bool $instant): Listing
    {
        return (new Listing())
            ->setHost((new User())->setFirstName('Host')->setLastName('Test')->setEmail('host@test.local'))
            ->setStatus('published')
            ->setMaxGuests(4)
            ->setPricePerNight('100.00')
            ->setCleaningFee('0.00')
            ->setServiceFee('0.00')
            ->setCurrency('EUR')
            ->setInstantBooking($instant);
    }

    private function makeAvailable(): void
    {
        $this->blockRepository->method('hasBlockOverlap')->willReturn(false);
        $this->bookingRepository->method('hasConfirmedOverlap')->willReturn(false);
    }

    public function testInstantBookingIsConfirmedImmediately(): void
    {
        $listing = $this->listing(true);
        $this->listingRepository->method('findForUpdate')->willReturn($listing);
        $this->makeAvailable();

        $booking = $this->service->createBooking($listing, new User(), DateRange::fromStrings('2026-07-10', '2026-07-13'), 2);

        self::assertSame(BookingStatus::Confirmed, $booking->getBookingStatus());
        self::assertSame('300.00', $booking->getTotalAmount());
        self::assertNotNull($booking->getConfirmedAt());
        self::assertCount(1, $this->dispatched);
        self::assertInstanceOf(BookingConfirmedMessage::class, $this->dispatched[0]);
    }

    public function testOnRequestBookingIsPending(): void
    {
        $listing = $this->listing(false);
        $this->listingRepository->method('findForUpdate')->willReturn($listing);
        $this->makeAvailable();

        $booking = $this->service->createBooking($listing, new User(), DateRange::fromStrings('2026-07-10', '2026-07-13'), 2);

        self::assertSame(BookingStatus::Pending, $booking->getBookingStatus());
        self::assertNull($booking->getConfirmedAt());
        self::assertInstanceOf(BookingCreatedMessage::class, $this->dispatched[0]);
    }

    public function testCreatingOnUnavailableDatesThrows(): void
    {
        $listing = $this->listing(true);
        $this->listingRepository->method('findForUpdate')->willReturn($listing);
        $this->blockRepository->method('hasBlockOverlap')->willReturn(false);
        $this->bookingRepository->method('hasConfirmedOverlap')->willReturn(true); // conflit

        $this->expectException(UnavailableDatesException::class);

        $this->service->createBooking($listing, new User(), DateRange::fromStrings('2026-07-10', '2026-07-13'), 2);
    }

    public function testCancelMarksBookingCancelledWithReasonAndAuthor(): void
    {
        $actor = new User();
        $booking = (new Booking())
            ->setListing($this->listing(true))
            ->setGuest(new User())
            ->setBookingStatus(BookingStatus::Confirmed);

        $this->service->cancel($booking, $actor, 'Changement de programme');

        self::assertSame(BookingStatus::Cancelled, $booking->getBookingStatus());
        self::assertSame('Changement de programme', $booking->getCancellationReason());
        self::assertSame($actor, $booking->getCancelledBy());
        self::assertNotNull($booking->getCancelledAt());
        self::assertInstanceOf(BookingCancelledMessage::class, $this->dispatched[0]);
    }
}
