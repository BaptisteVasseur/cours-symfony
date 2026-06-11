<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie qu'un utilisateur authentifié mais dépourvu du rôle requis est
 * redirigé vers l'accueil avec un message flash (et non vers une page 403).
 */
final class AccessControlTest extends WebTestCase
{
    public function testGuestWithoutHostRoleIsRedirectedHome(): void
    {
        $client = static::createClient();
        $guest = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->findOneBy(['email' => 'sophie.chen@email.com']);
        self::assertInstanceOf(User::class, $guest);
        self::assertNotContains('ROLE_HOST', $guest->getRoles(), 'Le voyageur ne doit pas avoir ROLE_HOST.');

        $client->loginUser($guest);
        $client->request('GET', '/compte/demandes'); // espace hôte

        self::assertResponseRedirects('/');
        // Le handler a déposé un message flash d'erreur (rendu par le layout app).
        $flashes = $client->getRequest()->getSession()->getFlashBag()->peekAll();
        self::assertArrayHasKey('error', $flashes);
        self::assertStringContainsString('Accès refusé', $flashes['error'][0]);
    }

    public function testGuestWithoutAdminRoleIsRedirectedHome(): void
    {
        $client = static::createClient();
        $guest = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->findOneBy(['email' => 'sophie.chen@email.com']);
        self::assertInstanceOf(User::class, $guest);

        $client->loginUser($guest);
        $client->request('GET', '/admin'); // espace admin

        self::assertResponseRedirects('/');
    }
}
