<?php

declare(strict_types=1);

namespace App\Tests\Controller\Host;

use App\Tests\Support\ReservationFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AvailabilityPageTest extends WebTestCase
{
    use ReservationFactoryTrait;

    private ?EntityManagerInterface $em = null;

    protected function tearDown(): void
    {
        if ($this->em !== null && $this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
        parent::tearDown();
    }

    public function testLaPageDisponibilitesSAfficheAvecSesSections(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        $property = $this->makePublishedProperty($this->em);
        $client->loginUser($property->getHost());

        $crawler = $client->request('GET', sprintf('/compte/logements/%s/disponibilites', $property->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Disponibilités');
        self::assertSelectorTextContains('main', 'Déclarer une indisponibilité');
        self::assertSelectorTextContains('main', 'Périodes bloquées ce mois-ci');
        self::assertSelectorTextContains('main', 'Synchronisation calendrier');
        self::assertSelectorTextContains('main', 'Générer le lien de synchronisation');
        self::assertCount(1, $crawler->filter('table'));
    }

    public function testLeLienIcalEstAfficheQuandUnTokenExiste(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        $property = $this->makePublishedProperty($this->em);
        $property->setIcalExportToken(str_repeat('b', 64));
        $this->em->flush();

        $client->loginUser($property->getHost());
        $crawler = $client->request('GET', sprintf('/compte/logements/%s/disponibilites', $property->getId()));

        self::assertResponseIsSuccessful();
        $feedInput = $crawler->filter('input[readonly]');
        self::assertCount(1, $feedInput);
        self::assertStringContainsString(
            sprintf('/api/properties/%s/calendar.ics?token=%s', $property->getId(), str_repeat('b', 64)),
            (string) $feedInput->attr('value'),
        );
        self::assertSelectorTextContains('main', 'Régénérer le lien');
        self::assertSelectorTextContains('main', 'Révoquer le lien');
    }
}
