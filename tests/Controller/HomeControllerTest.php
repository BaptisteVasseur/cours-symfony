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

    public function testHomeCategoryFiltersAreLinks(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/?category=seaside"]');
        $this->assertSelectorExists('a[href="/?category=iconic"]');
        $this->assertSelectorExists('a[href="/?category=trending"]');
        $this->assertSelectorExists('a[href="/?category=camping"]');
    }

    public function testSearchCategoryFilterLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search?category=camping');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[aria-current="page"] span', 'Campings');
        $this->assertSelectorTextContains('main', 'Filtre : Campings');
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

        $property = null;
        $checkin = null;
        $checkout = null;

        for ($offset = 1; $offset <= 365; ++$offset) {
            $candidateCheckin = new \DateTimeImmutable(sprintf('+%d days', $offset));
            $candidateCheckout = $candidateCheckin->modify('+4 days');
            $properties = $propertyRepository->searchAvailable(null, $candidateCheckin, $candidateCheckout, 1);

            foreach ($properties as $candidate) {
                if ($candidate->getHost()?->getId() !== $guest->getId()) {
                    $property = $candidate;
                    $checkin = $candidateCheckin;
                    $checkout = $candidateCheckout;
                    break 2;
                }
            }
        }

        $this->assertNotNull($property);
        $this->assertNotNull($checkin);
        $this->assertNotNull($checkout);

        $client->loginUser($guest);

        $crawler = $client->request('GET', '/logement/' . $property->getId() . '/reserver');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Confirmer la réservation')->form([
            'booking[checkinDate]' => $checkin->format('Y-m-d'),
            'booking[checkoutDate]' => $checkout->format('Y-m-d'),
            'booking[guestsCount]' => 1,
        ]);

        $client->submit($form);
        $this->assertResponseRedirects();

        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Confirmation de réservation');
    }
}
