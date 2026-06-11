<?php

declare(strict_types=1);

namespace App\Tests\Controller\Front;

use App\Entity\Property;
use App\Entity\User;
use App\Repository\PropertyRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BookingControllerTest extends WebTestCase
{
    public function testRefusChevauchementAfficheUnMessage(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $guest = $container->get(UserRepository::class)->findOneBy(['email' => 'lucas.bernard@email.com']);
        self::assertInstanceOf(User::class, $guest);

        /** @var Property|null $property */
        $property = $container->get(PropertyRepository::class)->findOneBy(['title' => 'Maison Test — Vue Mer']);
        self::assertInstanceOf(Property::class, $property);

        $client->loginUser($guest);

        $crawler = $client->request('GET', sprintf('/logement/%s/reserver', $property->getId()));
        self::assertResponseIsSuccessful();

        // Dates qui chevauchent la réservation confirmée (16 -> 19) seedée par les fixtures.
        $form = $crawler->selectButton('Confirmer la réservation')->form([
            'booking[checkinDate]' => '2026-06-17',
            'booking[checkoutDate]' => '2026-06-20',
            'booking[guestsCount]' => '2',
        ]);

        $client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Ces dates sont déjà réservées.');
    }

    public function testRefusDatesPasseesAfficheUnMessage(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $guest = $container->get(UserRepository::class)->findOneBy(['email' => 'lucas.bernard@email.com']);
        $property = $container->get(PropertyRepository::class)->findOneBy(['title' => 'Maison Test — Vue Mer']);
        self::assertInstanceOf(User::class, $guest);
        self::assertInstanceOf(Property::class, $property);

        $client->loginUser($guest);

        $crawler = $client->request('GET', sprintf('/logement/%s/reserver', $property->getId()));
        self::assertResponseIsSuccessful();

        $yesterday = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');
        $form = $crawler->selectButton('Confirmer la réservation')->form([
            'booking[checkinDate]' => $yesterday,
            'booking[checkoutDate]' => (new \DateTimeImmutable('+2 days'))->format('Y-m-d'),
            'booking[guestsCount]' => '2',
        ]);

        $client->submit($form);

        // Auparavant : 500 sans message. Désormais : message clair affiché au voyageur.
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'La période ne peut pas commencer dans le passé.');
    }
}
