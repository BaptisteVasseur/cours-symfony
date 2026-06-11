<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Reservation;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use App\Message\CheckinReminderMessage;
use App\MessageHandler\CheckinReminderHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckInReminderTest extends KernelTestCase
{
    public function testSendCheckInRemindersCommandDispatchesMessages(): void
    {
        $kernel = self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $userRepository = static::getContainer()->get(UserRepository::class);
        $propertyRepository = static::getContainer()->get(PropertyRepository::class);
        $reservationRepository = static::getContainer()->get(ReservationRepository::class);

        $guest = $userRepository->findOneBy(['email' => 'jeanmarc.dupont@email.com']);
        $this->assertNotNull($guest);

        $property = $propertyRepository->findOneBy([]);
        $this->assertNotNull($property);

        $tomorrow = (new \DateTimeImmutable('tomorrow'))->setTime(0, 0, 0);

        $existing = $reservationRepository->findOneBy([
            'property' => $property,
            'checkinDate' => $tomorrow,
            'status' => 'confirmed',
        ]);
        if ($existing !== null) {
            $entityManager->remove($existing);
            $entityManager->flush();
        }

        $reservation = new Reservation();
        $reservation->setProperty($property);
        $reservation->setGuest($guest);
        $reservation->setCheckinDate($tomorrow);
        $reservation->setCheckoutDate($tomorrow->modify('+2 days'));
        $reservation->setGuestsCount(2);
        $reservation->setStatus('confirmed');
        $reservation->setTotalPrice('150.00');
        $reservation->setCurrency('EUR');

        $entityManager->persist($reservation);
        $entityManager->flush();

        $application = new Application($kernel);
        $command = $application->find('app:booking:send-reminders');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('message(s) de rappel de check-in distribué(s).', $commandTester->getDisplay());

        $handler = static::getContainer()->get(CheckinReminderHandler::class);
        $message = new CheckinReminderMessage($reservation->getId()->toRfc4122());

        $handler($message);

        $notifRepo = $entityManager->getRepository(\App\Entity\Notification::class);
        $notif = $notifRepo->findOneBy([
            'user' => $guest,
            'title' => 'Rappel de check-in',
        ]);
        $this->assertNotNull($notif);
        $this->assertStringContainsString($property->getTitle(), $notif->getContent());

        $entityManager->remove($notif);
        $entityManager->remove($reservation);
        $entityManager->flush();
    }
}
