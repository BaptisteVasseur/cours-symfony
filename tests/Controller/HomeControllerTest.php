<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    public function testIndexPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('a span', 'airbnb');
    }

    public function testSearchPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search');

        $this->assertResponseIsSuccessful();
    }

    public function testDetailPageRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/logement/some-invalid-uuid');

        $this->assertResponseRedirects('/login');
    }

    public function testRegistrationSubmit(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(\App\Repository\UserRepository::class);
        $entityManager = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $existingUser = $userRepository->findOneBy(['email' => 'john.doe.test@example.com']);
        if ($existingUser !== null) {
            $entityManager->remove($existingUser);
            $entityManager->flush();
        }

        $crawler = $client->request('GET', '/register');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('S\'inscrire')->form([
            'registration[firstName]' => 'John',
            'registration[lastName]' => 'Doe',
            'registration[email]' => 'john.doe.test@example.com',
            'registration[plainPassword][first]' => 'supersecret123',
            'registration[plainPassword][second]' => 'supersecret123',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/login');

        $userRepository = static::getContainer()->get(\App\Repository\UserRepository::class);
        $user = $userRepository->findOneBy(['email' => 'john.doe.test@example.com']);
        $this->assertNotNull($user);
        $this->assertEquals('John', $user->getProfile()?->getFirstName());
    }

    public function testBookingSubmit(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(\App\Repository\UserRepository::class);
        $propertyRepository = static::getContainer()->get(\App\Repository\PropertyRepository::class);

        $guest = $userRepository->findOneBy(['email' => 'test@example.com']);
        $this->assertNotNull($guest);

        $property = $propertyRepository->findOneBy(['status' => 'published']);
        $this->assertNotNull($property);

        if ($property->getHost()?->getId() === $guest->getId()) {
            $properties = $propertyRepository->findBy(['status' => 'published']);
            foreach ($properties as $p) {
                if ($p->getHost()?->getId() !== $guest->getId()) {
                    $property = $p;
                    break;
                }
            }
        }

        $client->loginUser($guest);

        $crawler = $client->request('GET', '/logement/' . $property->getId() . '/reserver');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Confirmer la réservation')->form([
            'booking[checkinDate]' => (new \DateTimeImmutable('+1 day'))->format('Y-m-d'),
            'booking[checkoutDate]' => (new \DateTimeImmutable('+5 days'))->format('Y-m-d'),
            'booking[guestsCount]' => 1,
        ]);

        $client->submit($form);
        $this->assertResponseRedirects();

        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Confirmation de réservation');
    }
}
