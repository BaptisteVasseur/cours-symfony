<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\PropertyRepository;
use App\Repository\UserRepository;
use App\Service\BookingService;
use App\Service\ReservationWorkflowService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ReservationWorkflowTest extends KernelTestCase
{
    private BookingService $bookingService;
    private ReservationWorkflowService $workflowService;
    private UserRepository $userRepository;
    private PropertyRepository $propertyRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->bookingService = $container->get(BookingService::class);
        $this->workflowService = $container->get(ReservationWorkflowService::class);
        $this->userRepository = $container->get(UserRepository::class);
        $this->propertyRepository = $container->get(PropertyRepository::class);
    }

    public function testInstantBookingCreatesConfirmedReservation(): void
    {
        $guest = $this->requireUser('sophie.chen@email.com');
        $property = $this->requireProperty('Maison Test — Vue Mer');
        self::assertTrue($property->isInstantBooking());

        $offset = random_int(100, 200);
        $checkin = new \DateTimeImmutable(sprintf('+%d days', $offset));
        $checkout = $checkin->modify('+3 days');

        $reservation = $this->bookingService->createReservation($property, $guest, $checkin, $checkout, 2);

        self::assertSame('confirmed', $reservation->getStatus());
    }

    public function testRequestBookingCreatesPendingReservation(): void
    {
        $guest = $this->requireUser('sophie.chen@email.com');
        $property = $this->requireProperty('Appartement Test — Centre');
        self::assertFalse($property->isInstantBooking());

        $offset = random_int(210, 300);
        $checkin = new \DateTimeImmutable(sprintf('+%d days', $offset));
        $checkout = $checkin->modify('+2 days');

        $reservation = $this->bookingService->createReservation($property, $guest, $checkin, $checkout, 2);

        self::assertSame('pending', $reservation->getStatus());
        self::assertNotNull($reservation->getExpiresAt());
    }

    public function testHostCanAcceptPendingReservation(): void
    {
        $host = $this->requireUser('test@example.com');
        $guest = $this->requireUser('lucas.bernard@email.com');
        $property = $this->requireProperty('Appartement Test — Centre');

        $offset = random_int(310, 400);
        $checkin = new \DateTimeImmutable(sprintf('+%d days', $offset));
        $checkout = $checkin->modify('+2 days');

        $reservation = $this->bookingService->createReservation($property, $guest, $checkin, $checkout, 2);
        self::assertSame('pending', $reservation->getStatus());

        $this->workflowService->confirm($reservation, $host);

        self::assertSame('confirmed', $reservation->getStatus());
    }

    public function testCancellationRequiresReasonAndUpdatesStatus(): void
    {
        $guest = $this->requireUser('sophie.chen@email.com');
        $property = $this->requireProperty('Maison Test — Vue Mer');

        $offset = random_int(410, 500);
        $checkin = new \DateTimeImmutable(sprintf('+%d days', $offset));
        $checkout = $checkin->modify('+2 days');

        $reservation = $this->bookingService->createReservation($property, $guest, $checkin, $checkout, 1);
        self::assertSame('confirmed', $reservation->getStatus());

        $this->workflowService->cancel($reservation, $guest, 'Changement de plans');

        self::assertSame('cancelled', $reservation->getStatus());
        self::assertSame('Changement de plans', $reservation->getCancellationReason());
    }

    private function requireUser(string $email): User
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function requireProperty(string $title): \App\Entity\Property
    {
        $property = $this->propertyRepository->findOneBy(['title' => $title]);
        self::assertNotNull($property);

        return $property;
    }
}
