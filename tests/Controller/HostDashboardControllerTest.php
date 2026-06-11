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
        $this->assertSelectorTextContains('main a', 'Tableau de bord');
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
}
