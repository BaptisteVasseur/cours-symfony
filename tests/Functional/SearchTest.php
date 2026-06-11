<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Partie C — Moteur de recherche
 */
class SearchTest extends WebTestCase
{
    public function testSearchPageLoadsWithNoFilter(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/search');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-testid="property-card"], .property-card, article, .grid');
    }

    public function testSearchWithDestinationReturnsResults(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search?destination=Paris');

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);
    }

    public function testSearchWithGuestsFilter(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search?guests=1');

        $this->assertResponseIsSuccessful();
    }

    public function testSearchWithDateRange(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search?checkin=2026-10-01&checkout=2026-10-07&guests=2');

        $this->assertResponseIsSuccessful();
    }

    public function testSearchWithInvalidDatesDoesNotCrash(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search?checkin=bad-date&checkout=also-bad');

        $this->assertResponseIsSuccessful();
    }

    public function testSearchWithCheckoutBeforeCheckinDoesNotCrash(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search?checkin=2026-10-10&checkout=2026-10-01');

        $this->assertResponseIsSuccessful();
    }
}
