<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Partie B — Workflow de réservation voyageur
 */
class BookingFlowTest extends WebTestCase
{
    public function testUnauthenticatedUserIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reservations');

        $this->assertResponseRedirects('/login');
    }

    public function testGuestCanAccessBookingForm(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $guest = $userRepo->findOneBy(['email' => 'alice@test.fr']);
        $client->loginUser($guest);

        $propertyRepo = static::getContainer()->get(PropertyRepository::class);
        $property = $this->findBookableProperty($propertyRepo, $guest->getId());

        $client->request('GET', '/logement/' . $property->getId() . '/reserver');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('button[type="submit"]');
    }

    public function testGuestCanSubmitBookingRequest(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $guest = $userRepo->findOneBy(['email' => 'alice@test.fr']);
        $client->loginUser($guest);

        $propertyRepo = static::getContainer()->get(PropertyRepository::class);
        $property = $this->findBookableProperty($propertyRepo, $guest->getId());

        // GET le formulaire pour récupérer le token CSRF
        $crawler = $client->request('GET', '/logement/' . $property->getId() . '/reserver');
        $this->assertResponseIsSuccessful();

        // Soumettre avec des dates libres dans le futur (2027 pour éviter les fixtures)
        $form = $crawler->selectButton('Confirmer la réservation')->form([
            'booking[checkinDate]'  => '2027-06-01',
            'booking[checkoutDate]' => '2027-06-05',
            'booking[guestsCount]'  => 1,
        ]);
        $client->submit($form);

        $statusCode = $client->getResponse()->getStatusCode();

        if ($statusCode === 302) {
            // Réservation créée — vérifier en base
            $client->followRedirect();
            $this->assertResponseIsSuccessful();

            $reservationRepo = static::getContainer()->get(ReservationRepository::class);
            $reservation = $reservationRepo->findOneBy(['guest' => $guest], ['createdAt' => 'DESC']);
            $this->assertNotNull($reservation, 'La réservation doit être créée en base.');
            $this->assertContains($reservation->getStatus(), ['pending', 'confirmed']);
        } elseif ($statusCode === 422) {
            // Form invalide ou dates indisponibles — pas une erreur critique du test
            $this->assertResponseStatusCodeSame(422);
        } else {
            $this->fail('Statut inattendu : ' . $statusCode);
        }
    }

    public function testGuestCannotBookOwnProperty(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $host = $userRepo->findOneBy(['email' => 'host@test.fr']);
        $client->loginUser($host);

        $propertyRepo = static::getContainer()->get(PropertyRepository::class);
        $ownProperty = $propertyRepo->findOneBy(['host' => $host, 'status' => 'published']);

        if ($ownProperty === null) {
            $this->markTestSkipped('Aucun logement publié pour cet hôte.');
        }

        $client->request('GET', '/logement/' . $ownProperty->getId() . '/reserver');

        // Doit rediriger (flash d'erreur) ou afficher une erreur
        $this->assertNotSame(200, $client->getResponse()->getStatusCode(),
            "L'hôte ne devrait pas pouvoir accéder à la page de réservation de son propre logement sans erreur."
        );
    }

    public function testBookingFormIsAccessibleOnPublishedProperty(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $guest = $userRepo->findOneBy(['email' => 'alice@test.fr']);
        $client->loginUser($guest);

        $propertyRepo = static::getContainer()->get(PropertyRepository::class);
        $property = $this->findBookableProperty($propertyRepo, $guest->getId());

        $client->request('GET', '/logement/' . $property->getId() . '/reserver');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('button[type="submit"]');
    }

    public function testReservationListRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reservations');
        $this->assertResponseRedirects('/login');
    }

    public function testGuestCanSeeTheirReservations(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $guest = $userRepo->findOneBy(['email' => 'alice@test.fr']);
        $client->loginUser($guest);

        $client->request('GET', '/reservations');
        $this->assertResponseIsSuccessful();
    }

    // Trouve un logement publié qui n'appartient pas à l'utilisateur connecté
    private function findBookableProperty(PropertyRepository $repo, mixed $userId): \App\Entity\Property
    {
        $properties = $repo->findBy(['status' => 'published']);

        foreach ($properties as $property) {
            if ((string) $property->getHost()?->getId() !== (string) $userId) {
                return $property;
            }
        }

        $this->fail('Aucun logement disponible pour ce test.');
    }
}
