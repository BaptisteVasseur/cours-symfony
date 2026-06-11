<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\PropertyRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Partie E — Export iCal
 */
class ICalExportTest extends WebTestCase
{
    public function testICalDownloadWithValidToken(): void
    {
        $client = static::createClient();
        $propertyRepo = static::getContainer()->get(PropertyRepository::class);

        $property = $propertyRepo->findOneBy(['status' => 'published']);
        $this->assertNotNull($property, 'Il faut au moins un logement publié.');
        $this->assertNotNull($property->getCalendarToken(), 'Le calendar_token doit être rempli.');

        $url = '/api/properties/' . $property->getId() . '/calendar.ics?token=' . $property->getCalendarToken();
        $client->request('GET', $url);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'text/calendar; charset=utf-8');

        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $content);
        $this->assertStringContainsString('END:VCALENDAR', $content);
    }

    public function testICalDownloadWithWrongTokenReturns403(): void
    {
        $client = static::createClient();
        $propertyRepo = static::getContainer()->get(PropertyRepository::class);

        $property = $propertyRepo->findOneBy(['status' => 'published']);
        $this->assertNotNull($property);

        $url = '/api/properties/' . $property->getId() . '/calendar.ics?token=faketoken123';
        $client->request('GET', $url);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testICalDownloadWithNoTokenReturns403(): void
    {
        $client = static::createClient();
        $propertyRepo = static::getContainer()->get(PropertyRepository::class);

        $property = $propertyRepo->findOneBy(['status' => 'published']);
        $this->assertNotNull($property);

        $url = '/api/properties/' . $property->getId() . '/calendar.ics';
        $client->request('GET', $url);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testICalContainsConfirmedReservationsAsVEvents(): void
    {
        $client = static::createClient();
        $propertyRepo = static::getContainer()->get(PropertyRepository::class);
        $reservationRepo = static::getContainer()->get(\App\Repository\ReservationRepository::class);

        // Trouver un logement qui a des réservations confirmées
        $properties = $propertyRepo->findBy(['status' => 'published']);
        $propertyWithReservations = null;

        foreach ($properties as $p) {
            $confirmed = $reservationRepo->findBy(['property' => $p, 'status' => 'confirmed']);
            if (!empty($confirmed)) {
                $propertyWithReservations = $p;
                break;
            }
        }

        if ($propertyWithReservations === null) {
            $this->markTestSkipped('Aucun logement avec réservation confirmée.');
        }

        $url = '/api/properties/' . $propertyWithReservations->getId() . '/calendar.ics?token=' . $propertyWithReservations->getCalendarToken();
        $client->request('GET', $url);

        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('BEGIN:VEVENT', $content);
        $this->assertStringContainsString('END:VEVENT', $content);
        $this->assertStringContainsString('DTSTART', $content);
        $this->assertStringContainsString('DTEND', $content);
    }
}
