<?php

declare(strict_types=1);

namespace App\Tests\Controller\Front;

use App\Tests\Support\ReservationFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SearchControllerTest extends WebTestCase
{
    use ReservationFactoryTrait;

    private ?EntityManagerInterface $em = null;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->em !== null && $this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
        parent::tearDown();
    }

    public function testFiltreParDestination(): void
    {
        $lyonTitle = 'Maison Lyon ' . uniqid('', true);
        $parisTitle = 'Studio Paris ' . uniqid('', true);
        $this->makePublishedProperty($this->em, city: 'Lyon', title: $lyonTitle);
        $this->makePublishedProperty($this->em, city: 'Paris', title: $parisTitle);

        $this->client->request('GET', '/search?destination=lyon');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString($lyonTitle, $content);
        self::assertStringNotContainsString($parisTitle, $content);
    }

    public function testFiltreParCapacite(): void
    {
        $smallTitle = 'Petit ' . uniqid('', true);
        $bigTitle = 'Grand ' . uniqid('', true);
        $this->makePublishedProperty($this->em, maxGuests: 2, city: 'Nice', title: $smallTitle);
        $this->makePublishedProperty($this->em, maxGuests: 6, city: 'Nice', title: $bigTitle);

        $this->client->request('GET', '/search?guests=4');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString($bigTitle, $content);
        self::assertStringNotContainsString($smallTitle, $content);
    }

    public function testFiltreParDisponibilite(): void
    {
        $base = (new \DateTimeImmutable('today'))->modify('+10 days');
        $title = 'DispoTest ' . uniqid('', true);
        $property = $this->makePublishedProperty($this->em, city: 'Bordeaux', title: $title);
        $guest = $this->makeUser($this->em, 'guest-' . uniqid('', true) . '@test.local');
        // réservation confirmée du jour +10 au +15 → occupe ces dates
        $this->makeReservation($this->em, $property, $guest, $base, $base->modify('+5 days'), 'confirmed');

        // plage qui chevauche : le logement doit être exclu
        $this->client->request('GET', sprintf(
            '/search?checkin=%s&checkout=%s',
            $base->format('Y-m-d'),
            $base->modify('+5 days')->format('Y-m-d'),
        ));
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString($title, (string) $this->client->getResponse()->getContent());

        // plage libre : le logement doit être présent
        $this->client->request('GET', sprintf(
            '/search?checkin=%s&checkout=%s',
            $base->modify('+20 days')->format('Y-m-d'),
            $base->modify('+23 days')->format('Y-m-d'),
        ));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($title, (string) $this->client->getResponse()->getContent());
    }
}
