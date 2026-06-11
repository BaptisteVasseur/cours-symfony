<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\Reservation;
use App\Enum\BookingStatus;
use App\Repository\PropertyRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CompleteBookingsCommandTest extends KernelTestCase
{
    public function testCompleteBookingsCommand(): void
    {
        $kernel = self::bootKernel();
        $container = static::getContainer();

        $entityManager = $container->get(EntityManagerInterface::class);
        $userRepository = $container->get(UserRepository::class);
        $propertyRepository = $container->get(PropertyRepository::class);

        $guest = $userRepository->findOneBy(['email' => 'jeanmarc.dupont@email.com']);
        $this->assertNotNull($guest);

        $property = $propertyRepository->findOneBy([]);
        $this->assertNotNull($property);

        $host = $property->getHost();
        $this->assertNotNull($host);

        $reservation = new Reservation();
        $reservation->setProperty($property);
        $reservation->setGuest($guest);
        $reservation->setHost($host);
        $reservation->setCheckinDate(new \DateTimeImmutable('-10 days'));
        $reservation->setCheckoutDate(new \DateTimeImmutable('-5 days'));
        $reservation->setGuestsCount(2);
        $reservation->setBookingStatus(BookingStatus::CONFIRMED);
        $reservation->setTotalPrice('300.00');
        $reservation->setCurrency('EUR');
        $reservation->setUpdatedAt(new \DateTimeImmutable());

        $entityManager->persist($reservation);
        $entityManager->flush();

        $application = new Application($kernel);
        $command = $application->find('app:booking:complete');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('reservation(s) marquee(s) comme completee(s).', $commandTester->getDisplay());

        $entityManager->refresh($reservation);
        $this->assertEquals(BookingStatus::COMPLETED, $reservation->getBookingStatus());

        $entityManager->remove($reservation);
        $entityManager->flush();
    }
}
