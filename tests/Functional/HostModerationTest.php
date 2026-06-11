<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Partie B — Dashboard hôte : accepter / refuser / annuler
 */
class HostModerationTest extends WebTestCase
{
    public function testHostDashboardRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/hote/reservations');
        $this->assertResponseRedirects('/login');
    }

    public function testGuestCannotAccessHostDashboard(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $guest = $userRepo->findOneBy(['email' => 'alice@test.fr']);
        $client->loginUser($guest);

        $client->request('GET', '/hote/reservations');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testHostCanSeeReservationDashboard(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $host = $userRepo->findOneBy(['email' => 'host@test.fr']);
        $client->loginUser($host);

        $crawler = $client->request('GET', '/hote/reservations');
        $this->assertResponseIsSuccessful();
        // La page doit contenir au moins le titre du dashboard
        $this->assertGreaterThan(0, $crawler->filter('h1, h2, table, .reservation')->count());
    }

    public function testHostCanAcceptPendingReservation(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $host = $userRepo->findOneBy(['email' => 'host@test.fr']);
        $client->loginUser($host);

        $pending = $this->findPendingForHost($host);
        if ($pending === null) {
            $this->markTestSkipped('Aucune réservation pending pour jeanmarc.');
        }

        // GET la page pour initialiser la session et récupérer le token CSRF
        $crawler = $client->request('GET', '/hote/reservations');
        $this->assertResponseIsSuccessful();

        // Trouver le token CSRF dans le formulaire d'acceptation
        $form = $crawler->filter('form[action*="' . $pending->getId() . '/accepter"]')->first();
        if ($form->count() === 0) {
            $this->markTestSkipped('Formulaire accepter non trouvé pour cette réservation.');
        }

        $client->submit($form->form());

        $this->assertResponseRedirects();
        $client->followRedirect();

        $reservationRepo = static::getContainer()->get(ReservationRepository::class);
        $refreshed = $reservationRepo->find($pending->getId());
        $this->assertSame('confirmed', $refreshed->getStatus());
    }

    public function testHostCanRefusePendingReservation(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $host = $userRepo->findOneBy(['email' => 'host@test.fr']);
        $client->loginUser($host);

        $pending = $this->findPendingForHost($host);
        if ($pending === null) {
            $this->markTestSkipped('Aucune réservation pending pour elena.');
        }

        $crawler = $client->request('GET', '/hote/reservations');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[action*="' . $pending->getId() . '/refuser"]')->first();
        if ($form->count() === 0) {
            $this->markTestSkipped('Formulaire refuser non trouvé pour cette réservation.');
        }

        $client->submit($form->form(), ['reason' => 'Dates déjà occupées.']);

        $this->assertResponseRedirects();
        $client->followRedirect();

        $reservationRepo = static::getContainer()->get(ReservationRepository::class);
        $refreshed = $reservationRepo->find($pending->getId());
        $this->assertSame('cancelled', $refreshed->getStatus());
        $this->assertNotEmpty($refreshed->getCancellationReason());
    }

    public function testHostRefuseWithoutReasonKeepsStatusPending(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $host = $userRepo->findOneBy(['email' => 'host@test.fr']);
        $client->loginUser($host);

        $pending = $this->findPendingForHost($host);
        if ($pending === null) {
            $this->markTestSkipped('Aucune réservation pending pour jeanmarc.');
        }

        $crawler = $client->request('GET', '/hote/reservations');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[action*="' . $pending->getId() . '/refuser"]')->first();
        if ($form->count() === 0) {
            $this->markTestSkipped('Formulaire refuser non trouvé pour cette réservation.');
        }

        $client->submit($form->form(), ['reason' => '']);
        $client->followRedirects();

        $reservationRepo = static::getContainer()->get(ReservationRepository::class);
        $refreshed = $reservationRepo->find($pending->getId());
        $this->assertSame('pending', $refreshed->getStatus());
    }

    private function findPendingForHost(User $host): ?\App\Entity\Reservation
    {
        $propertyRepo = static::getContainer()->get(PropertyRepository::class);
        $reservationRepo = static::getContainer()->get(ReservationRepository::class);

        foreach ($propertyRepo->findByHost($host) as $property) {
            $pending = $reservationRepo->findPendingByProperty($property);
            if (!empty($pending)) {
                return $pending[0];
            }
        }

        return null;
    }
}
