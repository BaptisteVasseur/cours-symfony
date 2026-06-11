<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Reservation;
use App\Enum\BookingStatus;
use App\Repository\PropertyRepository;
use App\Repository\UserRepository;
use App\Service\BookingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class BookingServiceTest extends KernelTestCase
{
    public function testTransitionsUpdateUpdatedAt(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $entityManager = $container->get(EntityManagerInterface::class);
        $bookingService = $container->get(BookingService::class);
        $userRepository = $container->get(UserRepository::class);
        $propertyRepository = $container->get(PropertyRepository::class);

        $guest = $userRepository->findOneBy(['email' => 'jeanmarc.dupont@email.com']);
        $this->assertNotNull($guest);

        $property = $propertyRepository->findOneBy([]);
        $this->assertNotNull($property);

        $host = $property->getHost();
        $this->assertNotNull($host);
        if ($host->getId() === $guest->getId()) {
            $guest = $userRepository->findOneBy(['email' => 'test@example.com']);
            $this->assertNotNull($guest);
        }

        // Create a reservation for our tests
        $reservation = new Reservation();
        $reservation->setProperty($property);
        $reservation->setGuest($guest);
        $reservation->setHost($host);
        $reservation->setCheckinDate(new \DateTimeImmutable('+30 days'));
        $reservation->setCheckoutDate(new \DateTimeImmutable('+33 days'));
        $reservation->setGuestsCount(2);
        $reservation->setBookingStatus(BookingStatus::PENDING);
        $reservation->setTotalPrice('300.00');
        $reservation->setCurrency('EUR');

        // Set updatedAt in the past to verify it gets updated
        $pastTime = new \DateTimeImmutable('-1 hour');
        $reservation->setUpdatedAt($pastTime);

        $entityManager->persist($reservation);
        $entityManager->flush();

        $this->assertEquals($pastTime->getTimestamp(), $reservation->getUpdatedAt()?->getTimestamp());

        // Test 1: Confirm transition
        $bookingService->confirm($reservation, $host);
        $this->assertEquals(BookingStatus::CONFIRMED, $reservation->getBookingStatus());
        $this->assertGreaterThan($pastTime->getTimestamp(), $reservation->getUpdatedAt()?->getTimestamp());

        // Reset updatedAt to past time for next test
        $reservation->setUpdatedAt($pastTime);
        // Force bookingStatus back to PENDING so we can refuse it
        $reservation->setBookingStatus(BookingStatus::PENDING);
        $entityManager->flush();

        // Test 2: Refuse transition
        $bookingService->refuse($reservation, $host, 'Refus de test');
        $this->assertEquals(BookingStatus::CANCELLED, $reservation->getBookingStatus());
        $this->assertGreaterThan($pastTime->getTimestamp(), $reservation->getUpdatedAt()?->getTimestamp());

        // Test 3: Cancel transition
        // Re-create a confirmed reservation
        $reservation2 = new Reservation();
        $reservation2->setProperty($property);
        $reservation2->setGuest($guest);
        $reservation2->setHost($host);
        $reservation2->setCheckinDate(new \DateTimeImmutable('+40 days'));
        $reservation2->setCheckoutDate(new \DateTimeImmutable('+43 days'));
        $reservation2->setGuestsCount(2);
        $reservation2->setBookingStatus(BookingStatus::CONFIRMED);
        $reservation2->setTotalPrice('300.00');
        $reservation2->setCurrency('EUR');
        $reservation2->setUpdatedAt($pastTime);

        $entityManager->persist($reservation2);
        $entityManager->flush();

        $bookingService->cancel($reservation2, $guest, 'Annulation de test');
        $this->assertEquals(BookingStatus::CANCELLED, $reservation2->getBookingStatus());
        $this->assertGreaterThan($pastTime->getTimestamp(), $reservation2->getUpdatedAt()?->getTimestamp());

        // Test 4: MarkCompleted transition
        // Re-create a confirmed reservation in the past
        $reservation3 = new Reservation();
        $reservation3->setProperty($property);
        $reservation3->setGuest($guest);
        $reservation3->setHost($host);
        $reservation3->setCheckinDate(new \DateTimeImmutable('-10 days'));
        $reservation3->setCheckoutDate(new \DateTimeImmutable('-7 days'));
        $reservation3->setGuestsCount(2);
        $reservation3->setBookingStatus(BookingStatus::CONFIRMED);
        $reservation3->setTotalPrice('300.00');
        $reservation3->setCurrency('EUR');
        $reservation3->setUpdatedAt($pastTime);

        $entityManager->persist($reservation3);
        $entityManager->flush();

        $bookingService->markCompleted($reservation3);
        $this->assertEquals(BookingStatus::COMPLETED, $reservation3->getBookingStatus());
        $this->assertGreaterThan($pastTime->getTimestamp(), $reservation3->getUpdatedAt()?->getTimestamp());

        // Clean up
        $entityManager->remove($reservation);
        $entityManager->remove($reservation2);
        $entityManager->remove($reservation3);
        $entityManager->flush();
    }

    public function testInstantBookingConfirmationUsesSystemActor(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $entityManager = $container->get(EntityManagerInterface::class);
        $bookingService = $container->get(BookingService::class);
        $userRepository = $container->get(UserRepository::class);
        $propertyRepository = $container->get(PropertyRepository::class);

        $guest = $userRepository->findOneBy(['email' => 'jeanmarc.dupont@email.com']);
        $this->assertNotNull($guest);

        $property = $propertyRepository->findOneBy([]);
        $this->assertNotNull($property);

        $host = $property->getHost();
        $this->assertNotNull($host);
        if ($host->getId() === $guest->getId()) {
            $guest = $userRepository->findOneBy(['email' => 'test@example.com']);
            $this->assertNotNull($guest);
        }

        // Set the property to instant booking temporarily
        $originalInstantBooking = $property->isInstantBooking();
        $property->setInstantBooking(true);
        $entityManager->flush();

        try {
            // Create a reservation - it should be automatically confirmed with system actor
            $reservation = $bookingService->create(
                $property,
                $guest,
                new \DateTimeImmutable('+50 days'),
                new \DateTimeImmutable('+53 days'),
                2
            );

            $this->assertEquals(BookingStatus::CONFIRMED, $reservation->getBookingStatus());

            // Check history
            $historyRepo = $entityManager->getRepository(\App\Entity\ReservationStatusHistory::class);
            $histories = $historyRepo->findBy(['reservation' => $reservation], ['createdAt' => 'ASC']);
            $this->assertCount(2, $histories); // PENDING and CONFIRMED

            $pendingHistory = $histories[0];
            $this->assertEquals(BookingStatus::PENDING, $pendingHistory->getToStatus());
            $this->assertEquals('guest', $pendingHistory->getActor());

            $confirmedHistory = $histories[1];
            $this->assertEquals(BookingStatus::CONFIRMED, $confirmedHistory->getToStatus());
            $this->assertEquals('system', $confirmedHistory->getActor());
        } finally {
            // Restore property state and clean up reservation
            if (isset($reservation)) {
                $entityManager->remove($reservation);
            }
            $property->setInstantBooking($originalInstantBooking);
            $entityManager->flush();
        }
    }
}
