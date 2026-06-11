<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Booking;
use App\Entity\Listing;
use App\Entity\User;
use App\Enum\BookingStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Test fonctionnel du rappel de check-in (bonus G.2) : la commande cible une
 * arrivée du lendemain, l'envoie une seule fois (idempotence), puis ne renvoie
 * plus rien au second passage.
 */
final class CheckinReminderCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->rollback();
        }
        parent::tearDown();
    }

    private function commandTester(): CommandTester
    {
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('app:bookings:checkin-reminder'));
    }

    private function createConfirmedBookingArrivingTomorrow(): Booking
    {
        $uid = uniqid('', true);
        $host = (new User())->setFirstName('H')->setLastName('T')->setEmail("h-$uid@test.local")
            ->setPasswordHash('x')->setRole('host')->setEmailVerified(true)->setPhoneVerified(false)
            ->setIdentityVerified(false)->setStatus('active')->setCreatedAt(new \DateTimeImmutable())->setUpdatedAt(new \DateTimeImmutable());
        $guest = (new User())->setFirstName('G')->setLastName('T')->setEmail("g-$uid@test.local")
            ->setPasswordHash('x')->setRole('guest')->setEmailVerified(true)->setPhoneVerified(false)
            ->setIdentityVerified(false)->setStatus('active')->setCreatedAt(new \DateTimeImmutable())->setUpdatedAt(new \DateTimeImmutable());
        $listing = (new Listing())->setHost($host)->setTitle('Logement rappel')->setPricePerNight('100.00')
            ->setMaxGuests(4)->setStatus('published')->setInstantBooking(true)
            ->setCreatedAt(new \DateTimeImmutable())->setUpdatedAt(new \DateTimeImmutable());

        $tomorrow = (new \DateTimeImmutable())->setTime(0, 0)->modify('+1 day');
        $booking = (new Booking())->setListing($listing)->setGuest($guest)
            ->setCheckIn($tomorrow)->setCheckOut($tomorrow->modify('+3 days'))
            ->setGuestsCount(2)->setNightsCount(3)->setBaseAmount('300.00')->setTotalAmount('300.00')
            ->setCurrency('EUR')->setBookingStatus(BookingStatus::Confirmed)->setConfirmedAt(new \DateTimeImmutable())
            ->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($host);
        $this->em->persist($guest);
        $this->em->persist($listing);
        $this->em->persist($booking);
        $this->em->flush();

        return $booking;
    }

    public function testReminderIsSentOnceForTomorrowArrival(): void
    {
        $booking = $this->createConfirmedBookingArrivingTomorrow();
        $tester = $this->commandTester();

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        self::assertStringContainsString('rappel(s) de check-in', $tester->getDisplay());
        self::assertNotNull($booking->getCheckinReminderSentAt(), 'Le rappel doit être marqué comme envoyé.');
    }

    public function testReminderIsNotSentTwice(): void
    {
        $this->createConfirmedBookingArrivingTomorrow();

        $this->commandTester()->execute([]); // 1er passage : envoie
        $second = $this->commandTester();
        $second->execute([]);                // 2nd passage : plus rien

        self::assertStringContainsString('Aucun rappel à envoyer', $second->getDisplay());
    }
}
