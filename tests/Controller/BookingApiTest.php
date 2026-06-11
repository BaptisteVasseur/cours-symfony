<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Property;
use App\Entity\User;
use App\Entity\Reservation;
use App\Repository\UserRepository;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class BookingApiTest extends WebTestCase
{
    public function testPostBookingGoesThroughBookingServiceAndSavesReservation(): void
    {
        $client = static::createClient();
        
        $userRepository = static::getContainer()->get(UserRepository::class);
        $propertyRepository = static::getContainer()->get(PropertyRepository::class);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Fetch a user to act as guest
        $guest = $userRepository->findOneBy(['email' => 'jeanmarc.dupont@email.com']);
        $this->assertNotNull($guest);

        // Fetch a property owned by someone else
        $qb = $propertyRepository->createQueryBuilder('p');
        $property = $qb->andWhere('p.host != :host')
            ->setParameter('host', $guest)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        $this->assertNotNull($property);

        // Authenticate the guest
        $client->loginUser($guest);

        // Prepare checkin/checkout dates
        $checkin = new \DateTimeImmutable('tomorrow');
        $checkout = $checkin->modify('+2 days');

        // Let's do a POST request to /api/bookings
        $client->request('POST', '/api/bookings', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode([
            'property' => '/api/properties/' . $property->getId(),
            'guest' => '/api/users/' . $guest->getId(),
            'checkinDate' => $checkin->format('Y-m-d'),
            'checkoutDate' => $checkout->format('Y-m-d'),
            'guestsCount' => 1,
            'totalPrice' => '100.00', // BookingService will recalculate anyway
            'currency' => 'EUR',
        ]));

        $this->assertResponseIsSuccessful();
        $responseContent = json_decode($client->getResponse()->getContent() ?: '{}', true);

        // Since it went through BookingService, the persisted entity should be of type Reservation
        $reservationId = $responseContent['id'];
        $this->assertNotNull($reservationId);

        // Check in the database via ReservationRepository (which searches Reservation subclass only)
        $reservationRepository = static::getContainer()->get(ReservationRepository::class);
        $reservation = $reservationRepository->find($reservationId);
        $this->assertInstanceOf(Reservation::class, $reservation);
    }

    public function testPostBookingConflictReturnsHttp409(): void
    {
        $client = static::createClient();
        
        $userRepository = static::getContainer()->get(UserRepository::class);
        $propertyRepository = static::getContainer()->get(PropertyRepository::class);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Fetch a user to act as guest
        $guest = $userRepository->findOneBy(['email' => 'jeanmarc.dupont@email.com']);
        $this->assertNotNull($guest);

        // Fetch a property owned by someone else
        $qb = $propertyRepository->createQueryBuilder('p');
        $property = $qb->andWhere('p.host != :host')
            ->setParameter('host', $guest)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        $this->assertNotNull($property);

        // Add a confirmed reservation for these dates
        $checkin = new \DateTimeImmutable('tomorrow + 10 days');
        $checkout = $checkin->modify('+2 days');

        $existingReservation = new Reservation();
        $existingReservation->setProperty($property);
        $existingReservation->setGuest($guest);
        $existingReservation->setCheckinDate($checkin);
        $existingReservation->setCheckoutDate($checkout);
        $existingReservation->setGuestsCount(1);
        $existingReservation->setStatus(\App\Enum\BookingStatus::CONFIRMED);
        $existingReservation->setTotalPrice('200.00');
        $existingReservation->setCurrency('EUR');
        $existingReservation->setHost($property->getHost());
        $existingReservation->setUpdatedAt(new \DateTimeImmutable());
        $em->persist($existingReservation);
        $em->flush();

        // Authenticate the guest
        $client->loginUser($guest);

        // Let's do a POST request to /api/bookings for overlapping dates
        $client->request('POST', '/api/bookings', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode([
            'property' => '/api/properties/' . $property->getId(),
            'guest' => '/api/users/' . $guest->getId(),
            'checkinDate' => $checkin->format('Y-m-d'),
            'checkoutDate' => $checkout->format('Y-m-d'),
            'guestsCount' => 1,
            'totalPrice' => '100.00',
            'currency' => 'EUR',
        ]));

        // Expected status code is 409 Conflict
        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testPostBookingDuplicateForSameGuestReturnsError(): void
    {
        $client = static::createClient();
        
        $userRepository = static::getContainer()->get(UserRepository::class);
        $propertyRepository = static::getContainer()->get(PropertyRepository::class);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Fetch a user to act as guest
        $guest = $userRepository->findOneBy(['email' => 'jeanmarc.dupont@email.com']);
        $this->assertNotNull($guest);

        // Fetch a property owned by someone else
        $qb = $propertyRepository->createQueryBuilder('p');
        $property = $qb->andWhere('p.host != :host')
            ->setParameter('host', $guest)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        $this->assertNotNull($property);

        // Authenticate the guest
        $client->loginUser($guest);

        // Prepare checkin/checkout dates
        $checkin = new \DateTimeImmutable('tomorrow + 20 days');
        $checkout = $checkin->modify('+2 days');

        // Create the first pending reservation
        $client->request('POST', '/api/bookings', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode([
            'property' => '/api/properties/' . $property->getId(),
            'guest' => '/api/users/' . $guest->getId(),
            'checkinDate' => $checkin->format('Y-m-d'),
            'checkoutDate' => $checkout->format('Y-m-d'),
            'guestsCount' => 1,
            'totalPrice' => '100.00',
            'currency' => 'EUR',
        ]));
        $this->assertResponseIsSuccessful();

        // Attempt to create a duplicate pending reservation for the same dates and guest
        $client->request('POST', '/api/bookings', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode([
            'property' => '/api/properties/' . $property->getId(),
            'guest' => '/api/users/' . $guest->getId(),
            'checkinDate' => $checkin->format('Y-m-d'),
            'checkoutDate' => $checkout->format('Y-m-d'),
            'guestsCount' => 1,
            'totalPrice' => '100.00',
            'currency' => 'EUR',
        ]));

        // Expected error response is 409 Conflict because of duplicate active booking
        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }
}
