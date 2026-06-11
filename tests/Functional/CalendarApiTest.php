<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\ListingRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CalendarApiTest extends WebTestCase
{
    public function testValidTokenReturnsCalendar(): void
    {
        $client = static::createClient();
        $listing = static::getContainer()->get(ListingRepository::class)->findOneBy([]);
        self::assertNotNull($listing, 'Les fixtures doivent fournir au moins un logement.');

        $client->request('GET', sprintf(
            '/api/properties/%s/calendar.ics?token=%s',
            $listing->getId(),
            $listing->getCalendarToken()
        ));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/calendar; charset=utf-8');
        self::assertStringContainsString('BEGIN:VCALENDAR', $client->getResponse()->getContent());
    }

    public function testInvalidTokenIsForbidden(): void
    {
        $client = static::createClient();
        $listing = static::getContainer()->get(ListingRepository::class)->findOneBy([]);

        $client->request('GET', sprintf(
            '/api/properties/%s/calendar.ics?token=%s',
            $listing->getId(),
            'wrong-token'
        ));

        self::assertResponseStatusCodeSame(403);
    }

    public function testMissingTokenIsForbidden(): void
    {
        $client = static::createClient();
        $listing = static::getContainer()->get(ListingRepository::class)->findOneBy([]);

        $client->request('GET', sprintf('/api/properties/%s/calendar.ics', $listing->getId()));

        self::assertResponseStatusCodeSame(403);
    }
}
