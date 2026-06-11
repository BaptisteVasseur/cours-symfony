<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\PropertyRepository;
use App\Service\PricingService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PropertySearchTest extends KernelTestCase
{
    private PropertyRepository $propertyRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->propertyRepository = static::getContainer()->get(PropertyRepository::class);
    }

    public function testSearchByCity(): void
    {
        $results = $this->propertyRepository->searchAvailable('Biarritz', null, null, null);
        self::assertNotEmpty($results);
        self::assertSame('Maison Test — Vue Mer', $results[0]->getTitle());
    }

    public function testSearchByAddressLine(): void
    {
        $property = $this->propertyRepository->findOneBy(['title' => 'Maison Test — Vue Mer']);
        self::assertNotNull($property);
        $line = $property->getAddress()?->getAddressLine1();
        self::assertNotNull($line);

        $results = $this->propertyRepository->searchAvailable(substr($line, 0, 8), null, null, null);
        self::assertNotEmpty($results);
    }

    public function testSearchExcludesUnavailableDates(): void
    {
        $property = $this->propertyRepository->findOneBy(['title' => 'Maison Test — Vue Mer']);
        self::assertNotNull($property);

        $checkin = new \DateTimeImmutable('+5 days');
        $checkout = $checkin->modify('+3 days');

        $results = $this->propertyRepository->searchAvailable(null, $checkin, $checkout, 2);
        $ids = array_map(static fn ($p) => $p->getId(), $results);

        self::assertNotContains($property->getId(), $ids);
    }

    public function testSearchFiltersByGuests(): void
    {
        $results = $this->propertyRepository->searchAvailable(null, null, null, 99);
        self::assertSame([], $results);
    }
}
