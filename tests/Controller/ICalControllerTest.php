<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Property;
use App\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ICalControllerTest extends WebTestCase
{
    public function testCalendarExportRequiresValidToken(): void
    {
        $client = static::createClient();
        $property = $this->publishedProperty();

        $client->request('GET', sprintf('/api/properties/%s/calendar.ics', $property->getId()));

        $this->assertResponseStatusCodeSame(401);
        $this->assertSame('Unauthorized', $client->getResponse()->getContent());
    }

    public function testCalendarExportReturnsConfirmedReservationsAsIcs(): void
    {
        $client = static::createClient();
        $property = $this->publishedProperty();
        $token = str_repeat('a', 64);

        $property->setIcalToken($token);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', sprintf('/api/properties/%s/calendar.ics?token=%s', $property->getId(), $token));

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('text/calendar', $client->getResponse()->headers->get('Content-Type') ?? '');

        $content = $client->getResponse()->getContent() ?: '';
        $this->assertStringContainsString("BEGIN:VCALENDAR\r\n", $content);
        $this->assertStringContainsString("VERSION:2.0\r\n", $content);
        $this->assertStringContainsString("PRODID:-//Clone Airbnb//FR\r\n", $content);
        $this->assertStringContainsString("CALSCALE:GREGORIAN\r\n", $content);
        $this->assertStringContainsString('UID:res-', $content);
        $this->assertStringContainsString('SUMMARY:'.$property->getTitle(), $content);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $content);
    }

    private function publishedProperty(): Property
    {
        $property = static::getContainer()
            ->get(PropertyRepository::class)
            ->findOneBy(['title' => 'Loft Industriel Vue Mer']);

        $this->assertInstanceOf(Property::class, $property);

        return $property;
    }
}
