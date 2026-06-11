<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HostDashboardControllerTest extends WebTestCase
{
    public function testDashboardPageRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/compte/hote/dashboard');

        $this->assertResponseRedirects('/login');
    }

    public function testDashboardPageLoadsForHost(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $host = $userRepository->findOneBy(['email' => 'test@example.com']);
        $this->assertNotNull($host);

        $client->loginUser($host);
        $client->request('GET', '/compte/hote/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Bonjour');
        $this->assertSelectorTextContains('main nav a, main div a[href*="dashboard"]', 'Tableau de bord');
    }

    public function testReservationsPageLoadsForHost(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $host = $userRepository->findOneBy(['email' => 'test@example.com']);
        $this->assertNotNull($host);

        $client->loginUser($host);
        $client->request('GET', '/compte/hote/reservations');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Réservations reçues');
    }

    public function testBecomeHostAccess(): void
    {
        $client = static::createClient();
        $client->request('GET', '/compte/devenir-hote');

        $this->assertResponseRedirects('/login');
    }

    public function testContactHostAsGuest(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneBy(['email' => 'test@example.com']);
        $this->assertNotNull($testUser);

        $reservationRepository = static::getContainer()->get(\App\Repository\ReservationRepository::class);
        $reservation = $reservationRepository->findOneBy(['guest' => $testUser]);
        $this->assertNotNull($reservation);

        $client->loginUser($testUser);
        $client->request('GET', sprintf('/compte/reservations/%s/contact', $reservation->getId()));

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testConfirmBookingConflictReturnsHttp409(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $userRepository = static::getContainer()->get(UserRepository::class);
        
        $host = $userRepository->findOneBy(['email' => 'jeanmarc.dupont@email.com']);
        $this->assertNotNull($host);

        $propertyRepository = static::getContainer()->get(\App\Repository\PropertyRepository::class);
        $property = $propertyRepository->findOneBy(['host' => $host]);
        $this->assertNotNull($property);
        $property->setStatus('published');
        $property->setMaxGuests(5);
        $em->persist($property);
        $em->flush();

        $checkin = new \DateTimeImmutable('tomorrow + 365 days');
        $checkout = $checkin->modify('+3 days');

        $guest = $userRepository->findOneBy(['email' => 'test@example.com']);
        $this->assertNotNull($guest);

        $booking1 = new \App\Entity\Reservation();
        $booking1->setProperty($property);
        $booking1->setGuest($guest);
        $booking1->setHost($host);
        $booking1->setCheckinDate($checkin);
        $booking1->setCheckoutDate($checkout);
        $booking1->setGuestsCount(1);
        $booking1->setStatus(\App\Enum\BookingStatus::PENDING);
        $booking1->setTotalPrice('150.00');
        $booking1->setCurrency('EUR');
        $booking1->setUpdatedAt(new \DateTimeImmutable());

        $booking2 = new \App\Entity\Reservation();
        $booking2->setProperty($property);
        $booking2->setGuest($guest);
        $booking2->setHost($host);
        $booking2->setCheckinDate($checkin);
        $booking2->setCheckoutDate($checkout);
        $booking2->setGuestsCount(1);
        $booking2->setStatus(\App\Enum\BookingStatus::PENDING);
        $booking2->setTotalPrice('150.00');
        $booking2->setCurrency('EUR');
        $booking2->setUpdatedAt(new \DateTimeImmutable());

        $em->persist($booking1);
        $em->persist($booking2);
        $em->flush();

        $client->loginUser($host);

        // Récupérer la page des réservations hôte
        $crawler = $client->request('GET', '/compte/hote/reservations');
        $this->assertResponseIsSuccessful();

        // Trouver et soumettre le formulaire d'acceptation de la première réservation
        $form1 = $crawler->filter(sprintf('form[action*="/%s/"]', $booking1->getId()))->form();
        $client->submit($form1);
        $this->assertResponseRedirects('/host/bookings');
        
        $em->clear();
        
        // Recharger la page (ou utiliser le crawler actuel) pour récupérer le deuxième formulaire
        $crawler = $client->request('GET', '/compte/hote/reservations');
        $form2 = $crawler->filter(sprintf('form[action*="/%s/"]', $booking2->getId()))->form();
        $client->submit($form2);

        $this->assertResponseStatusCodeSame(\Symfony\Component\HttpFoundation\Response::HTTP_CONFLICT);
    }
}
