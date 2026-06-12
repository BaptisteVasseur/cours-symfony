<?php

declare(strict_types=1);

namespace App\Tests\Controller\Front;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Tests\Support\ReservationFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BookingCreationTest extends WebTestCase
{
    use ReservationFactoryTrait;

    private ?EntityManagerInterface $em = null;

    protected function tearDown(): void
    {
        if ($this->em !== null && $this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
        parent::tearDown();
    }

    public function testReservationInstantaneeEstConfirmee(): void
    {
        [$client, $guest, $property] = $this->prepare(instantBooking: true);

        $this->submitBooking($client, $property);

        $reservation = $this->reservationFor($guest, $property);
        self::assertInstanceOf(Reservation::class, $reservation);
        self::assertSame('confirmed', $reservation->getStatus());
        // notification dispatchée en asynchrone (transport in-memory en test)
        self::assertNotEmpty(static::getContainer()->get('messenger.transport.async')->getSent());
    }

    public function testReservationNonInstantaneeEstPending(): void
    {
        [$client, $guest, $property] = $this->prepare(instantBooking: false);

        $this->submitBooking($client, $property);

        $reservation = $this->reservationFor($guest, $property);
        self::assertInstanceOf(Reservation::class, $reservation);
        self::assertSame('pending', $reservation->getStatus());
    }

    /**
     * @return array{0: KernelBrowser, 1: User, 2: Property}
     */
    private function prepare(bool $instantBooking): array
    {
        $client = static::createClient();
        $client->disableReboot();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        $property = $this->makePublishedProperty($this->em, $instantBooking);
        $guest = $this->makeUser($this->em, 'guest-' . uniqid('', true) . '@test.local');
        $this->em->flush();

        $client->loginUser($guest);

        return [$client, $guest, $property];
    }

    private function submitBooking(KernelBrowser $client, Property $property): void
    {
        $crawler = $client->request('GET', sprintf('/logement/%s/reserver', $property->getId()));
        self::assertResponseIsSuccessful();

        $checkin = (new \DateTimeImmutable('today'))->modify('+30 days');
        $form = $crawler->selectButton('Confirmer la réservation')->form([
            'booking[checkinDate]' => $checkin->format('Y-m-d'),
            'booking[checkoutDate]' => $checkin->modify('+3 days')->format('Y-m-d'),
            'booking[guestsCount]' => '2',
        ]);
        $client->submit($form);
    }

    private function reservationFor(User $guest, Property $property): ?Reservation
    {
        return static::getContainer()
            ->get(ReservationRepository::class)
            ->findOneBy(['guest' => $guest, 'property' => $property]);
    }
}
