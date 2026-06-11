<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Message\ReservationCancelledNotification;
use App\Message\ReservationConfirmedNotification;
use App\Message\ReservationRejectedNotification;
use App\Message\ReservationRequestedNotification;
use App\Service\Availability\AvailabilityChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Tests de bout en bout du parcours de réservation (énoncé §B) :
 *  - B.1 bis : réservation sur demande -> En attente + notification hôte
 *  - B.1 ter : réserver son propre logement -> refus
 *  - B.2     : modération hôte (accepter / refuser avec motif / refuser sans motif)
 *  - B.3     : annulation par le voyageur -> Annulée + dates libérées
 *
 * (B.1 réservation instantanée et B.1 ter dates indisponibles sont couverts par BookingTest.)
 */
final class ReservationFlowTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /** B.1 bis — logement sur demande => statut pending + ReservationRequestedNotification. */
    public function testOnRequestBookingCreatesPendingAndNotifiesHost(): void
    {
        $guest = $this->user('sophie.chen@email.com');
        $property = $this->publishedProperty(false, $guest);

        $this->client->loginUser($guest);
        $this->client->request('GET', $this->bookUrl($property));
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Confirmer la réservation', [
            'booking[checkinDate]' => '2027-06-10',
            'booking[checkoutDate]' => '2027-06-14',
            'booking[guestsCount]' => '2',
        ]);

        self::assertResponseRedirects();
        $reservation = $this->em()->getRepository(Reservation::class)->findOneBy(['guest' => $guest, 'property' => $property], ['createdAt' => 'DESC']);
        self::assertInstanceOf(Reservation::class, $reservation);
        self::assertSame('pending', $reservation->getStatus());
        $this->assertMessageDispatched(ReservationRequestedNotification::class);
    }

    /** B.1 ter — un hôte ne peut pas réserver son propre logement. */
    public function testHostCannotBookOwnProperty(): void
    {
        $property = $this->anyPublishedProperty();
        $host = $property->getHost();
        self::assertInstanceOf(User::class, $host);

        $this->client->loginUser($host);
        $this->client->request('GET', $this->bookUrl($property));

        self::assertResponseRedirects($this->detailUrl($property));
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'propre logement');
    }

    /** B.2 — l'hôte accepte une demande => Confirmée + ReservationConfirmedNotification. */
    public function testHostAcceptsPendingRequest(): void
    {
        [$reservation, $host] = $this->createPending();

        $this->client->loginUser($host);
        $crawler = $this->client->request('GET', '/compte/demandes');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/compte/demandes/' . $reservation->getId() . '/accepter"]')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/compte/demandes');
        self::assertSame('confirmed', $this->refresh($reservation)->getStatus());
        $this->assertMessageDispatched(ReservationConfirmedNotification::class);
    }

    /** B.2 — l'hôte refuse avec motif => Annulée + motif + ReservationRejectedNotification. */
    public function testHostRejectsWithReason(): void
    {
        [$reservation, $host] = $this->createPending();

        $this->client->loginUser($host);
        $crawler = $this->client->request('GET', '/compte/demandes');

        $form = $crawler->filter('form[action="/compte/demandes/' . $reservation->getId() . '/refuser"]')->form();
        $form['reason'] = 'Logement indisponible pour maintenance.';
        $this->client->submit($form);

        self::assertResponseRedirects('/compte/demandes');
        $fresh = $this->refresh($reservation);
        self::assertSame('cancelled', $fresh->getStatus());
        self::assertSame('Logement indisponible pour maintenance.', $fresh->getCancellationReason());
        $this->assertMessageDispatched(ReservationRejectedNotification::class);
    }

    /** B.2 — refuser sans motif est bloqué (la demande reste En attente). */
    public function testHostRejectWithoutReasonIsBlocked(): void
    {
        [$reservation, $host] = $this->createPending();

        $this->client->loginUser($host);
        $crawler = $this->client->request('GET', '/compte/demandes');
        $token = $crawler->filter('form[action="/compte/demandes/' . $reservation->getId() . '/refuser"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/compte/demandes/' . $reservation->getId() . '/refuser', [
            '_token' => $token,
            'reason' => '   ',
        ]);

        self::assertResponseRedirects('/compte/demandes');
        self::assertSame('pending', $this->refresh($reservation)->getStatus());
    }

    /** B.3 — le voyageur annule une réservation confirmée => Annulée + dates libérées. */
    public function testGuestCancelsConfirmedReservationAndFreesDates(): void
    {
        $guest = $this->user('lucas.bernard@email.com');
        $property = $this->publishedProperty(true, $guest);
        $reservation = $this->persistReservation($property, $guest, 'confirmed', '2027-09-01', '2027-09-05');

        // Dates initialement indisponibles (réservation confirmée).
        $checker = static::getContainer()->get(AvailabilityChecker::class);
        self::assertFalse($checker->isAvailable($property, new \DateTimeImmutable('2027-09-01'), new \DateTimeImmutable('2027-09-05'), 2));

        $this->client->loginUser($guest);
        $crawler = $this->client->request('GET', '/reservations/' . $reservation->getId());
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/reservations/' . $reservation->getId() . '/annuler"]')->form();
        $form['reason'] = 'Changement de programme.';
        $this->client->submit($form);

        self::assertResponseRedirects('/reservations/' . $reservation->getId());
        self::assertSame('cancelled', $this->refresh($reservation)->getStatus());
        $this->assertMessageDispatched(ReservationCancelledNotification::class);

        // Dates de nouveau disponibles après annulation.
        $this->em()->clear();
        $freshProperty = $this->em()->getRepository(Property::class)->find($property->getId());
        self::assertTrue($checker->isAvailable($freshProperty, new \DateTimeImmutable('2027-09-01'), new \DateTimeImmutable('2027-09-05'), 2));
    }

    // ---------------------------------------------------------------- helpers

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function user(string $email): User
    {
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function publishedProperty(bool $instant, User $notOwnedBy): Property
    {
        $property = $this->em()->createQueryBuilder()
            ->select('p')->from(Property::class, 'p')
            ->where('p.status = :s')->andWhere('p.instantBooking = :i')->andWhere('p.host != :u')
            ->setParameter('s', 'published')->setParameter('i', $instant)->setParameter('u', $notOwnedBy)
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
        self::assertInstanceOf(Property::class, $property);

        return $property;
    }

    private function anyPublishedProperty(): Property
    {
        $property = $this->em()->createQueryBuilder()
            ->select('p')->from(Property::class, 'p')
            ->where('p.status = :s')
            ->setParameter('s', 'published')
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
        self::assertInstanceOf(Property::class, $property);

        return $property;
    }

    /**
     * @return array{0: Reservation, 1: User} la réservation pending et l'hôte du logement
     */
    private function createPending(): array
    {
        $guest = $this->user('sophie.chen@email.com');
        $property = $this->publishedProperty(false, $guest);
        $reservation = $this->persistReservation($property, $guest, 'pending', '2027-07-10', '2027-07-14');

        return [$reservation, $property->getHost()];
    }

    private function persistReservation(Property $property, User $guest, string $status, string $checkin, string $checkout): Reservation
    {
        $reservation = new Reservation();
        $reservation->setProperty($property);
        $reservation->setGuest($guest);
        $reservation->setCheckinDate(new \DateTimeImmutable($checkin));
        $reservation->setCheckoutDate(new \DateTimeImmutable($checkout));
        $reservation->setGuestsCount(2);
        $reservation->setStatus($status);
        $reservation->setTotalPrice('400.00');
        $reservation->setCurrency('EUR');
        $this->em()->persist($reservation);
        $this->em()->flush();

        return $reservation;
    }

    private function refresh(Reservation $reservation): Reservation
    {
        $id = $reservation->getId();
        $this->em()->clear();
        $fresh = $this->em()->getRepository(Reservation::class)->find($id);
        self::assertInstanceOf(Reservation::class, $fresh);

        return $fresh;
    }

    private function assertMessageDispatched(string $messageClass): void
    {
        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $dispatched = array_filter(
            array_map(static fn ($envelope) => $envelope->getMessage(), $transport->getSent()),
            static fn ($message) => $message instanceof $messageClass,
        );
        self::assertNotEmpty($dispatched, sprintf('Un message %s devait être dispatché.', $messageClass));
    }

    private function bookUrl(Property $property): string
    {
        return sprintf('/logement/%s/reserver', $property->getId());
    }

    private function detailUrl(Property $property): string
    {
        return sprintf('/logement/%s', $property->getId());
    }
}
