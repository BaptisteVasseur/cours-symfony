<?php

declare(strict_types=1);

namespace App\Tests\Service\Availability;

use App\Entity\CancellationPolicy;
use App\Entity\Property;
use App\Entity\PropertyUnavailability;
use App\Entity\Reservation;
use App\Entity\User;
use App\Enum\UnavailabilityReason;
use App\Service\Availability\AvailabilityChecker;
use App\Service\Availability\AvailabilityFailureReason;
use App\Service\Availability\Exception\PropertyNotAvailableException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AvailabilityCheckerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private AvailabilityChecker $checker;
    private \DateTimeImmutable $base;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->checker = $container->get(AvailabilityChecker::class);

        // Chaque test s'exécute dans une transaction annulée en tearDown (aucune donnée persistée).
        // En DBAL 4, les transactions imbriquées utilisent des savepoints par défaut : le rollback
        // interne de assertAvailableWithLock ne corrompt donc pas la transaction externe.
        $this->em->getConnection()->beginTransaction();

        $this->base = (new \DateTimeImmutable('today'))->modify('+10 days');
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
        $this->em->close();
        parent::tearDown();
    }

    public function testChevauchementExact(): void
    {
        $property = $this->createPublishedProperty(maxGuests: 4);
        $this->createConfirmedReservation($property, $this->day(0), $this->day(5));

        $result = $this->checker->check($property, $this->day(0), $this->day(5), 2);

        self::assertFalse($result->isAvailable());
        self::assertSame(AvailabilityFailureReason::CHEVAUCHEMENT, $result->getReason());
    }

    public function testChevauchementPartielDebut(): void
    {
        $property = $this->createPublishedProperty();
        $this->createConfirmedReservation($property, $this->day(5), $this->day(10));

        $result = $this->checker->check($property, $this->day(3), $this->day(7), 2);

        self::assertSame(AvailabilityFailureReason::CHEVAUCHEMENT, $result->getReason());
    }

    public function testChevauchementPartielFin(): void
    {
        $property = $this->createPublishedProperty();
        $this->createConfirmedReservation($property, $this->day(5), $this->day(10));

        $result = $this->checker->check($property, $this->day(8), $this->day(12), 2);

        self::assertSame(AvailabilityFailureReason::CHEVAUCHEMENT, $result->getReason());
    }

    public function testPlagesAdjacentesNeChevauchentPas(): void
    {
        // Convention [début, fin) : checkout == checkin voisin => pas de chevauchement.
        $property = $this->createPublishedProperty();
        $this->createConfirmedReservation($property, $this->day(5), $this->day(10));

        // Juste avant : [day0, day5) — checkout = jour d'arrivée de la résa existante.
        self::assertTrue($this->checker->check($property, $this->day(0), $this->day(5), 2)->isAvailable());
        // Juste après : [day10, day15) — checkin = jour de départ de la résa existante.
        self::assertTrue($this->checker->check($property, $this->day(10), $this->day(15), 2)->isAvailable());
    }

    public function testJoursBloques(): void
    {
        $property = $this->createPublishedProperty();
        $this->createUnavailability($property, $this->day(20), $this->day(25));

        $result = $this->checker->check($property, $this->day(21), $this->day(23), 2);

        self::assertSame(AvailabilityFailureReason::JOURS_BLOQUES, $result->getReason());
    }

    public function testLogementNonPublie(): void
    {
        $property = $this->createPublishedProperty();
        $property->setStatus('draft');
        $this->em->flush();

        $result = $this->checker->check($property, $this->day(0), $this->day(3), 2);

        self::assertSame(AvailabilityFailureReason::NON_PUBLIE, $result->getReason());
    }

    public function testCapaciteInsuffisante(): void
    {
        $property = $this->createPublishedProperty(maxGuests: 2);

        $result = $this->checker->check($property, $this->day(0), $this->day(3), 5);

        self::assertSame(AvailabilityFailureReason::CAPACITE_INSUFFISANTE, $result->getReason());
    }

    public function testPeriodeDisponible(): void
    {
        $property = $this->createPublishedProperty(maxGuests: 4);

        $result = $this->checker->check($property, $this->day(0), $this->day(4), 3);

        self::assertTrue($result->isAvailable());
        self::assertNull($result->getReason());
    }

    public function testDatesPasseesRefusees(): void
    {
        $property = $this->createPublishedProperty();
        $past = (new \DateTimeImmutable('today'))->modify('-2 days');

        $this->expectException(\InvalidArgumentException::class);
        $this->checker->check($property, $past, $past->modify('+2 days'), 2);
    }

    public function testAccesConcurrentUneSeuleReussit(): void
    {
        // Le verrou pessimiste sérialise les requêtes simultanées : on reproduit ici la séquence
        // imposée par le verrou (la 1re obtient le créneau, la 2nde voit le chevauchement).
        $property = $this->createPublishedProperty(maxGuests: 4);

        $winner = $this->newReservation($property, $this->day(0), $this->day(5));
        $this->checker->assertAvailableWithLock(
            $property,
            $this->day(0),
            $this->day(5),
            2,
            fn () => $this->em->persist($winner),
        );
        self::assertNotNull($winner->getId(), 'La première réservation doit aboutir.');

        $loser = $this->newReservation($property, $this->day(0), $this->day(5));
        $this->expectException(PropertyNotAvailableException::class);
        $this->checker->assertAvailableWithLock(
            $property,
            $this->day(0),
            $this->day(5),
            2,
            fn () => $this->em->persist($loser),
        );
    }

    private function day(int $offset): \DateTimeImmutable
    {
        return $this->base->modify(sprintf('%+d days', $offset));
    }

    private function createPublishedProperty(int $maxGuests = 4): Property
    {
        $host = $this->createUser('host-' . uniqid('', true) . '@test.local');
        $policy = (new CancellationPolicy())
            ->setCode('flex-' . uniqid('', true))
            ->setLabel('Flexible');

        $property = (new Property())
            ->setHost($host)
            ->setCancellationPolicy($policy)
            ->setTitle('Logement de test')
            ->setPropertyType('apartment')
            ->setStatus('published')
            ->setMaxGuests($maxGuests)
            ->setBedrooms(1)
            ->setBeds(1)
            ->setBathrooms(1)
            ->setPricePerNight('100.00');

        $this->em->persist($policy);
        $this->em->persist($property);
        $this->em->flush();

        return $property;
    }

    private function createUser(string $email): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPasswordHash('hash')
            ->setStatus('active');
        $this->em->persist($user);

        return $user;
    }

    private function newReservation(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): Reservation
    {
        return (new Reservation())
            ->setProperty($property)
            ->setGuest($this->createUser('guest-' . uniqid('', true) . '@test.local'))
            ->setCheckinDate($checkin)
            ->setCheckoutDate($checkout)
            ->setGuestsCount(2)
            ->setStatus('confirmed')
            ->setTotalPrice('100.00')
            ->setCurrency('EUR');
    }

    private function createConfirmedReservation(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): Reservation
    {
        $reservation = $this->newReservation($property, $checkin, $checkout);
        $this->em->persist($reservation);
        $this->em->flush();

        return $reservation;
    }

    private function createUnavailability(Property $property, \DateTimeImmutable $start, \DateTimeImmutable $end): PropertyUnavailability
    {
        $unavailability = (new PropertyUnavailability())
            ->setProperty($property)
            ->setStartDate($start)
            ->setEndDate($end)
            ->setReason(UnavailabilityReason::TRAVAUX);
        $this->em->persist($unavailability);
        $this->em->flush();

        return $unavailability;
    }
}
