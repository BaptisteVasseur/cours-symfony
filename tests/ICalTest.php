<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\AvailabilityException;
use App\Entity\Property;
use App\Entity\PropertyICalSync;
use App\Entity\Reservation;
use App\Service\ICalImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ICalTest extends WebTestCase
{
    public function testICalExport(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        // Récupérer une réservation confirmée ou complétée
        $reservation = $entityManager->getRepository(Reservation::class)->findOneBy(['status' => 'confirmed']);
        if ($reservation === null) {
            $reservation = $entityManager->getRepository(Reservation::class)->findOneBy(['status' => 'completed']);
        }
        $this->assertNotNull($reservation, 'Aucune réservation trouvée pour le test d\'export iCal');

        $property = $reservation->getProperty();
        $this->assertNotNull($property);

        $icalToken = (string) $property->getIcalToken();

        // Appeler la route d'export
        $client->request('GET', '/property/' . $icalToken . '/calendar.ics');

        $this->assertResponseIsSuccessful();
        $response = $client->getResponse();
        $this->assertStringContainsString('text/calendar', $response->headers->get('Content-Type'));
        
        $content = $response->getContent();
        $this->assertNotEmpty($content);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $content);
        $this->assertStringContainsString('VERSION:2.0', $content);
        $this->assertStringContainsString('SUMMARY:Réservation :', $content);
        $this->assertStringContainsString('DESCRIPTION:Propriété :', $content);
        $this->assertStringContainsString('END:VCALENDAR', $content);
    }

    public function testICalImport(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        // Récupérer une propriété
        $property = $entityManager->getRepository(Property::class)->findOneBy([]);
        $this->assertNotNull($property);

        // Mock du contenu iCal
        $icalContent = <<<ICAL
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Calendar//FR
BEGIN:VEVENT
UID:ical-import-test-event-uuid@test-provider
DTSTART:20260901T000000Z
DTEND:20260904T000000Z
SUMMARY:Réservation externe test
END:VEVENT
END:VCALENDAR
ICAL;

        // Mock du HttpClient et de la Response
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getContent')->willReturn($icalContent);

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->method('request')->willReturn($mockResponse);

        // Créer une synchro iCal de test en base
        $sync = new PropertyICalSync();
        $sync->setProperty($property);
        $sync->setProviderName('TestProvider');
        $sync->setICalUrl('https://example.com/calendar-' . uniqid() . '.ics');
        $sync->setSyncStatus('pending');
        
        $entityManager->persist($sync);
        $entityManager->flush();

        // Instancier ICalImportService avec le mock HttpClient
        $exceptionRepo = $entityManager->getRepository(AvailabilityException::class);
        $importService = new ICalImportService($mockHttpClient, $exceptionRepo, $entityManager);

        // Exécuter l'import
        $importService->importFromUrl($sync);

        // Vérifier que le statut de synchronisation est passé à success
        $this->assertSame('success', $sync->getSyncStatus());
        $this->assertNotNull($sync->getLastSyncAt());

        // Rafraîchir l'entityManager pour récupérer les exceptions créées
        $entityManager->clear();

        // Récupérer les exceptions importées pour cette propriété
        $exceptions = $entityManager->getRepository(AvailabilityException::class)->findBy([
            'property' => $property->getId(),
            'source' => AvailabilityException::SOURCE_ICAL_IMPORT
        ]);

        $this->assertNotEmpty($exceptions);

        // DTSTART: 2026-09-01 à DTEND: 2026-09-04 (exclu) doit créer 3 exceptions d'indisponibilité
        // 2026-09-01, 2026-09-02, 2026-09-03
        $this->assertCount(3, $exceptions);

        $dates = array_map(fn($e) => $e->getDate()->format('Y-m-d'), $exceptions);
        sort($dates);

        $this->assertSame(['2026-09-01', '2026-09-02', '2026-09-03'], $dates);
    }
}
