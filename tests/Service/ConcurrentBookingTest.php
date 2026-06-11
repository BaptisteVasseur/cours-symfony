<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use App\Service\BookingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Teste le scénario de concurrence : deux demandes simultanées sur le même logement et les mêmes dates.
 *
 * Résultat attendu sans protection : les deux réservations passent en pending (pas d'erreur).
 * Si l'hôte accepte la première → confirmed.
 * Toute nouvelle demande aux mêmes dates est alors bloquée par la vérification de chevauchement.
 */
class ConcurrentBookingTest extends TestCase
{
    private Property $property;
    private User $guest1;
    private User $guest2;
    private \DateTimeImmutable $checkIn;
    private \DateTimeImmutable $checkOut;

    protected function setUp(): void
    {
        $this->checkIn  = new \DateTimeImmutable('2027-08-01');
        $this->checkOut = new \DateTimeImmutable('2027-08-05');

        $this->property = $this->createProperty(instantBooking: false);
        $this->guest1   = $this->createUser('guest1@test.com');
        $this->guest2   = $this->createUser('guest2@test.com');
    }

    /**
     * Scénario 1 : deux demandes pending créées simultanément → les deux existent sans erreur.
     * Démontre l'absence de blocage au dépôt de demande (choix voulu).
     */
    public function testTwoPendingReservationsCanCoexist(): void
    {
        // Aucune réservation confirmée → pas de blocage
        $service = $this->buildService(existingReservations: []);

        $res1 = $service->createBooking($this->property, $this->guest1, $this->checkIn, $this->checkOut, 2);
        $res2 = $service->createBooking($this->property, $this->guest2, $this->checkIn, $this->checkOut, 2);

        $this->assertSame('pending', $res1->getStatus());
        $this->assertSame('pending', $res2->getStatus());
    }

    /**
     * Scénario 2 : la première demande est acceptée (confirmed).
     * Une nouvelle demande aux mêmes dates est bloquée → RuntimeException.
     * Démontre que la protection existe APRÈS confirmation, pas au dépôt.
     */
    public function testNewBookingBlockedWhenDatesAlreadyConfirmed(): void
    {
        $confirmed = $this->makeReservation($this->guest1, 'confirmed');

        $this->expectException(\RuntimeException::class);

        $this->buildService(existingReservations: [$confirmed])
            ->createBooking($this->property, $this->guest2, $this->checkIn, $this->checkOut, 2);
    }

    /**
     * Scénario 3 : instant booking — la première réservation est directement confirmed.
     * Une seconde demande simultanée est immédiatement rejetée.
     */
    public function testInstantBookingBlocksSecondRequest(): void
    {
        $property  = $this->createProperty(instantBooking: true);
        $confirmed = $this->makeReservation($this->guest1, 'confirmed');

        $this->expectException(\RuntimeException::class);

        $this->buildService(existingReservations: [$confirmed])
            ->createBooking($property, $this->guest2, $this->checkIn, $this->checkOut, 2);
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    /** @param list<Reservation> $existingReservations */
    private function buildService(array $existingReservations): BookingService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist');
        $em->method('flush');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(
            fn (object $message) => new Envelope($message)
        );

        $availabilityRepo = $this->createMock(PropertyAvailabilityRepository::class);
        $availabilityRepo->method('findBlockedInPeriod')->willReturn([]);
        $availabilityRepo->method('getMaxMinimumStayInPeriod')->willReturn(null);

        $reservationRepo = $this->createMock(ReservationRepository::class);
        $reservationRepo->method('findByPropertyAndPeriod')->willReturn($existingReservations);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('http://localhost/host/dashboard');

        return new BookingService($em, $bus, $availabilityRepo, $reservationRepo, $urlGenerator);
    }

    private function createProperty(bool $instantBooking): Property
    {
        $property = new Property();
        $property->setTitle('Logement test');
        $property->setPricePerNight('100.00');
        $property->setStatus('published');
        $property->setInstantBooking($instantBooking);
        return $property;
    }

    private function createUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPasswordHash('hashed');
        $user->setStatus('active');
        return $user;
    }

    private function makeReservation(User $guest, string $status): Reservation
    {
        $r = new Reservation();
        $r->setProperty($this->property);
        $r->setGuest($guest);
        $r->setCheckinDate($this->checkIn);
        $r->setCheckoutDate($this->checkOut);
        $r->setGuestsCount(2);
        $r->setStatus($status);
        $r->setTotalPrice('400.00');
        $r->setCreatedAt(new \DateTimeImmutable());
        $r->setUpdatedAt(new \DateTimeImmutable());
        $r->setCurrency('EUR');
        return $r;
    }
}
