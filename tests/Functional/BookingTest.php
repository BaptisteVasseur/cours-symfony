<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Message\ReservationConfirmedNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Vérifie de bout en bout le moteur de réservation : création en réservation
 * instantanée, dispatch d'une notification asynchrone, et rejet d'une demande
 * sur des dates déjà confirmées (algorithme de disponibilité).
 */
final class BookingTest extends WebTestCase
{
    public function testInstantBookingConfirmsAndDispatchesNotification(): void
    {
        $client = static::createClient();
        $guest = $this->guest();
        $property = $this->publishedInstantPropertyNotOwnedBy($guest);

        $client->loginUser($guest);

        $crawler = $client->request('GET', sprintf('/logement/%s/reserver', $property->getId()));
        self::assertResponseIsSuccessful();

        $client->submitForm('Confirmer la réservation', [
            'booking[checkinDate]' => '2027-05-10',
            'booking[checkoutDate]' => '2027-05-14',
            'booking[guestsCount]' => '2',
        ]);

        self::assertResponseRedirects();

        $reservation = $this->em()->getRepository(Reservation::class)->findOneBy([
            'guest' => $guest,
            'property' => $property,
        ]);
        self::assertNotNull($reservation, 'La réservation doit être créée.');
        self::assertSame('confirmed', $reservation->getStatus(), 'Réservation instantanée => confirmée.');

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $messages = array_map(static fn($envelope) => $envelope->getMessage(), $transport->getSent());
        self::assertContainsOnlyInstancesOf(ReservationConfirmedNotification::class, $messages);
        self::assertCount(1, $messages, 'Une notification de confirmation doit être dispatchée.');
    }

    public function testAlreadyBookedDatesAreRejected(): void
    {
        $client = static::createClient();
        $guest = $this->guest();
        $property = $this->publishedInstantPropertyNotOwnedBy($guest);

        // Une réservation confirmée occupe déjà la période.
        $blocking = new Reservation();
        $blocking->setProperty($property);
        $blocking->setGuest($guest);
        $blocking->setCheckinDate(new \DateTimeImmutable('2027-08-01'));
        $blocking->setCheckoutDate(new \DateTimeImmutable('2027-08-05'));
        $blocking->setGuestsCount(2);
        $blocking->setStatus('confirmed');
        $blocking->setTotalPrice('500.00');
        $blocking->setCurrency('EUR');
        $this->em()->persist($blocking);
        $this->em()->flush();

        $client->loginUser($guest);
        $client->request('GET', sprintf('/logement/%s/reserver', $property->getId()));
        $client->submitForm('Confirmer la réservation', [
            'booking[checkinDate]' => '2027-08-02', // chevauche [08-01, 08-05)
            'booking[checkoutDate]' => '2027-08-06',
            'booking[guestsCount]' => '2',
        ]);

        // Pas de redirection vers la confirmation : la page de réservation est
        // ré-affichée (statut 422 pour formulaire invalide) avec l'erreur visible.
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'déjà réservées');

        $count = $this->em()->getRepository(Reservation::class)->count([
            'guest' => $guest,
            'property' => $property,
            'checkinDate' => new \DateTimeImmutable('2027-08-02'),
        ]);
        self::assertSame(0, $count, 'Aucune réservation ne doit être créée sur des dates occupées.');
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function guest(): User
    {
        $guest = $this->em()->getRepository(User::class)->findOneBy(['email' => 'sophie.chen@email.com']);
        self::assertInstanceOf(User::class, $guest);

        return $guest;
    }

    private function publishedInstantPropertyNotOwnedBy(User $guest): Property
    {
        $property = $this->em()->createQueryBuilder()
            ->select('p')
            ->from(Property::class, 'p')
            ->where('p.status = :published')
            ->andWhere('p.instantBooking = true')
            ->andWhere('p.host != :guest')
            ->setParameter('published', 'published')
            ->setParameter('guest', $guest)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Property::class, $property, 'Un logement publié en réservation instantanée est requis.');

        return $property;
    }
}
