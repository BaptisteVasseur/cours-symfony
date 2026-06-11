<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Property;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\PropertyRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class NewFeaturesTest extends WebTestCase
{
    public function testHostRedirectedToHostDashboardOnLogin(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/login');
        
        $client->submitForm('Se connecter', [
            '_username' => 'jeanmarc.dupont@email.com',
            '_password' => 'password',
        ]);
        
        $this->assertResponseRedirects('/compte/hote/dashboard');
    }

    public function testAdminRedirectedToAdminDashboardOnLogin(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/login');
        
        $client->submitForm('Se connecter', [
            '_username' => 'moderation@airbnb-clone.fr',
            '_password' => 'password',
        ]);
        
        $this->assertResponseRedirects('/admin');
    }

    public function testAdminHasInAppNotificationsInAdminDashboard(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $admin = $userRepository->findOneBy(['email' => 'moderation@airbnb-clone.fr']);
        $this->assertNotNull($admin);

        $client->loginUser($admin);
        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-notifications-menu]');
    }

    public function testHostDashboardButtonHiddenForAdmin(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $admin = $userRepository->findOneBy(['email' => 'moderation@airbnb-clone.fr']);
        $this->assertNotNull($admin);

        $client->loginUser($admin);
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('a[href="/compte/hote/dashboard"]');
    }

    public function testHostDashboardButtonVisibleForHost(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $host = $userRepository->findOneBy(['email' => 'jeanmarc.dupont@email.com']);
        $this->assertNotNull($host);

        $client->loginUser($host);
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/compte/hote/dashboard"]');
    }

    public function testHostCanAccessNewPropertyPage(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $host = $userRepository->findOneBy(['email' => 'jeanmarc.dupont@email.com']);
        $this->assertNotNull($host);

        $client->loginUser($host);
        $client->request('GET', '/compte/hote/proprietes/nouvelle');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Créer une nouvelle annonce');
    }

    public function testToggleFavoriteProperty(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['email' => 'jeanmarc.dupont@email.com']);
        $this->assertNotNull($user);

        $propertyRepository = static::getContainer()->get(PropertyRepository::class);
        $property = $propertyRepository->findOneBy([]);
        $this->assertNotNull($property);

        $client->loginUser($user);
        
        $client->request('POST', sprintf('/logement/%s/favori', $property->getId()));
        $this->assertResponseIsSuccessful();
        
        $responseContent = json_decode($client->getResponse()->getContent() ?: '{}', true);
        $this->assertArrayHasKey('favorited', $responseContent);
    }

    public function testHostCanAccessEditPropertyPage(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $host = $userRepository->findOneBy(['email' => 'jeanmarc.dupont@email.com']);
        $this->assertNotNull($host);

        $propertyRepository = static::getContainer()->get(PropertyRepository::class);
        $property = $propertyRepository->findOneBy(['host' => $host]);
        $this->assertNotNull($property);

        $client->loginUser($host);
        $client->request('GET', sprintf('/compte/hote/proprietes/%s/modifier', $property->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Modifier l\'annonce');
    }

    public function testHostCanEditProperty(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $host = $userRepository->findOneBy(['email' => 'jeanmarc.dupont@email.com']);
        $this->assertNotNull($host);

        $propertyRepository = static::getContainer()->get(PropertyRepository::class);
        $property = $propertyRepository->findOneBy(['host' => $host]);
        $this->assertNotNull($property);

        $client->loginUser($host);
        $crawler = $client->request('GET', sprintf('/compte/hote/proprietes/%s/modifier', $property->getId()));
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer les modifications')->form();
        $form['host_property[title]'] = 'Titre Modifie';
        $client->submit($form);

        $this->assertResponseRedirects('/compte/proprietes');
        
        $entityManager = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $entityManager->clear();
        $updatedProperty = $propertyRepository->find($property->getId());
        $this->assertEquals('Titre Modifie', $updatedProperty->getTitle());
    }

    public function testNonHostCannotEditProperty(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        
        $host = $userRepository->findOneBy(['email' => 'jeanmarc.dupont@email.com']);
        $this->assertNotNull($host);
        
        $propertyRepository = static::getContainer()->get(PropertyRepository::class);
        $qb = $propertyRepository->createQueryBuilder('p');
        $otherProperty = $qb->andWhere('p.host != :host')
            ->setParameter('host', $host)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
            
        if ($otherProperty !== null) {
            $client->loginUser($host);
            $client->request('GET', sprintf('/compte/hote/proprietes/%s/modifier', $otherProperty->getId()));
            $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        }
    }
}

