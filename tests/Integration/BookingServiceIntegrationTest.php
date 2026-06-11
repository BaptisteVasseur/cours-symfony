<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Listing;
use App\Entity\User;
use App\Enum\BookingStatus;
use App\Exception\UnavailableDatesException;
use App\Service\AvailabilityService;
use App\Service\BookingService;
use App\ValueObject\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class BookingServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private BookingService $bookingService;
    private AvailabilityService $availability;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->bookingService = $container->get(BookingService::class);
        $this->availability = $container->get(AvailabilityService::class);

        // Isolation : tout ce qui est écrit pendant le test sera annulé.
        $this->em->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->rollback();
        }
        parent::tearDown();
    }

    private function persistListing(bool $instant): Listing
    {
        $host = (new User())
            ->setFirstName('Host')->setLastName('Test')
            ->setEmail('host-' . uniqid('', true) . '@test.local')
            ->setPasswordHash('x')->setRole('host')
            ->setEmailVerified(true)->setPhoneVerified(false)->setIdentityVerified(false)
            ->setStatus('active')->setCreatedAt(new \DateTimeImmutable())->setUpdatedAt(new \DateTimeImmutable());
        $this->em->persist($host);

        $listing = (new Listing())
            ->setHost($host)->setTitle('Logement test')->setPricePerNight('100.00')
            ->setCleaningFee('0.00')->setServiceFee('0.00')->setCurrency('EUR')
            ->setMaxGuests(4)->setStatus('published')->setInstantBooking($instant)
            ->setCreatedAt(new \DateTimeImmutable())->setUpdatedAt(new \DateTimeImmutable());
        $this->em->persist($listing);

        return $listing;
    }

    private function persistGuest(): User
    {
        $guest = (new User())
            ->setFirstName('Guest')->setLastName('Test')
            ->setEmail('guest-' . uniqid('', true) . '@test.local')
            ->setPasswordHash('x')->setRole('guest')
            ->setEmailVerified(true)->setPhoneVerified(false)->setIdentityVerified(false)
            ->setStatus('active')->setCreatedAt(new \DateTimeImmutable())->setUpdatedAt(new \DateTimeImmutable());
        $this->em->persist($guest);

        return $guest;
    }

    public function testInstantBookingIsConfirmedAndPersisted(): void
    {
        $listing = $this->persistListing(instant: true);
        $guest = $this->persistGuest();
        $this->em->flush();

        $booking = $this->bookingService->createBooking(
            $listing,
            $guest,
            DateRange::fromStrings('2026-09-10', '2026-09-13'),
            2,
        );

        self::assertNotNull($booking->getId());
        self::assertSame(BookingStatus::Confirmed, $booking->getBookingStatus());
        self::assertSame(3, $booking->getNightsCount());
        self::assertSame('300.00', $booking->getTotalAmount());
        // L'historique (timeline G.5) doit contenir au moins l'entrée de création.
        self::assertGreaterThanOrEqual(1, $booking->getHistories()->count());
    }

    public function testOnRequestBookingIsPending(): void
    {
        $listing = $this->persistListing(instant: false);
        $guest = $this->persistGuest();
        $this->em->flush();

        $booking = $this->bookingService->createBooking(
            $listing,
            $guest,
            DateRange::fromStrings('2026-09-10', '2026-09-13'),
            2,
        );

        self::assertSame(BookingStatus::Pending, $booking->getBookingStatus());
    }

    public function testOverlappingConfirmedBookingIsRejected(): void
    {
        $listing = $this->persistListing(instant: true);
        $guest1 = $this->persistGuest();
        $guest2 = $this->persistGuest();
        $this->em->flush();

        // 1ère réservation confirmée sur les mêmes dates.
        $this->bookingService->createBooking($listing, $guest1, DateRange::fromStrings('2026-09-10', '2026-09-13'), 2);

        // 2nde réservation chevauchante -> doit être refusée (anti-overbooking).
        $this->expectException(UnavailableDatesException::class);
        $this->bookingService->createBooking($listing, $guest2, DateRange::fromStrings('2026-09-12', '2026-09-15'), 2);
    }

    public function testCancellationFreesTheDates(): void
    {
        $listing = $this->persistListing(instant: true);
        $guest = $this->persistGuest();
        $this->em->flush();

        $range = DateRange::fromStrings('2026-10-01', '2026-10-05');
        $booking = $this->bookingService->createBooking($listing, $guest, $range, 2);

        // Occupé tant que confirmé...
        self::assertFalse($this->availability->isAvailable($listing, $range, 2));

        $this->bookingService->cancel($booking, $guest, 'Test annulation');

        // ...de nouveau disponible après annulation.
        self::assertSame(BookingStatus::Cancelled, $booking->getBookingStatus());
        self::assertTrue($this->availability->isAvailable($listing, $range, 2));
    }
}
