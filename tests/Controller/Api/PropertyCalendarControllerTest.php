<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Property;
use App\Tests\Support\ReservationFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PropertyCalendarControllerTest extends WebTestCase
{
    use ReservationFactoryTrait;

    private const TOKEN = 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private ?EntityManagerInterface $em = null;

    protected function tearDown(): void
    {
        if ($this->em !== null && $this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
        parent::tearDown();
    }

    public function testFluxAccessibleAnonymementAvecTokenValide(): void
    {
        [$client, $property] = $this->prepare();
        $confirmed = $this->makeReservation(
            $this->em,
            $property,
            $this->makeUser($this->em, 'guest-ical-' . uniqid('', true) . '@test.local'),
            new \DateTimeImmutable('2027-07-10'),
            new \DateTimeImmutable('2027-07-15'),
            'confirmed',
        );

        $client->request('GET', $this->feedUrl($property, self::TOKEN));

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertStringStartsWith('text/calendar', (string) $response->headers->get('Content-Type'));

        $body = (string) $response->getContent();
        self::assertStringContainsString("BEGIN:VCALENDAR\r\n", $body);
        self::assertStringContainsString('PRODID:-//Clone Airbnb//FR', $body);
        self::assertStringContainsString(sprintf('UID:res-%s@clone-airbnb.local', $confirmed->getId()), $body);
        self::assertStringContainsString('DTSTART;VALUE=DATE:20270710', $body);
        self::assertStringContainsString('DTEND;VALUE=DATE:20270715', $body);
        self::assertStringContainsString("END:VCALENDAR\r\n", $body);
    }

    public function testSeulesLesReservationsConfirmeesSontExportees(): void
    {
        [$client, $property] = $this->prepare();
        $guest = $this->makeUser($this->em, 'guest-ical-' . uniqid('', true) . '@test.local');
        $pending = $this->makeReservation($this->em, $property, $guest, new \DateTimeImmutable('2027-08-01'), new \DateTimeImmutable('2027-08-05'), 'pending');
        $cancelled = $this->makeReservation($this->em, $property, $guest, new \DateTimeImmutable('2027-09-01'), new \DateTimeImmutable('2027-09-05'), 'cancelled');
        $confirmed = $this->makeReservation($this->em, $property, $guest, new \DateTimeImmutable('2027-10-01'), new \DateTimeImmutable('2027-10-05'), 'confirmed');

        $client->request('GET', $this->feedUrl($property, self::TOKEN));

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString(sprintf('UID:res-%s@', $confirmed->getId()), $body);
        self::assertStringNotContainsString(sprintf('UID:res-%s@', $pending->getId()), $body);
        self::assertStringNotContainsString(sprintf('UID:res-%s@', $cancelled->getId()), $body);
    }

    public function testTokenAbsentOuInvalideRetourne404(): void
    {
        [$client, $property] = $this->prepare();

        $client->request('GET', sprintf('/api/properties/%s/calendar.ics', $property->getId()));
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', $this->feedUrl($property, 'mauvais-token'));
        self::assertResponseStatusCodeSame(404);
    }

    public function testTokenNullEtParametreVideRetourne404(): void
    {
        [$client, $property] = $this->prepare(token: null);

        $client->request('GET', sprintf('/api/properties/%s/calendar.ics?token=', $property->getId()));
        self::assertResponseStatusCodeSame(404);
    }

    public function testAncienTokenInvalideApresRegeneration(): void
    {
        [$client, $property] = $this->prepare();

        $newToken = str_repeat('f', 64);
        $property->setIcalExportToken($newToken);
        $this->em->flush();

        $client->request('GET', $this->feedUrl($property, self::TOKEN));
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', $this->feedUrl($property, $newToken));
        self::assertResponseIsSuccessful();
    }

    public function testIdentifiantMalformeRetourne404(): void
    {
        [$client] = $this->prepare();

        $client->request('GET', '/api/properties/pas-un-uuid/calendar.ics?token=x');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return array{0: KernelBrowser, 1: Property}
     */
    private function prepare(?string $token = self::TOKEN): array
    {
        $client = static::createClient();
        $client->disableReboot();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        $property = $this->makePublishedProperty($this->em);
        $property->setIcalExportToken($token);
        $this->em->flush();

        return [$client, $property];
    }

    private function feedUrl(Property $property, string $token): string
    {
        return sprintf('/api/properties/%s/calendar.ics?token=%s', $property->getId(), $token);
    }
}
