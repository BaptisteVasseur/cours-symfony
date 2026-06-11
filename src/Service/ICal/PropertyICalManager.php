<?php

declare(strict_types=1);

namespace App\Service\ICal;

use App\Entity\Property;
use App\Entity\PropertyICalSync;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PropertyICalManager
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function generateExportToken(Property $property): string
    {
        $token = bin2hex(random_bytes(32));
        $property->setICalExportToken($token);
        $this->entityManager->flush();

        return $token;
    }

    public function revokeExportToken(Property $property): void
    {
        $property->setICalExportToken(null);
        $this->entityManager->flush();
    }

    public function addImport(Property $property, string $providerName, string $iCalUrl): PropertyICalSync
    {
        $sync = new PropertyICalSync();
        $sync->setProperty($property);
        $sync->setProviderName($providerName);
        $sync->setICalUrl($iCalUrl);

        $this->entityManager->persist($sync);
        $this->entityManager->flush();

        return $sync;
    }

    public function removeImport(PropertyICalSync $sync): void
    {
        $this->entityManager->remove($sync);
        $this->entityManager->flush();
    }
}
