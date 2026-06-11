<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\PropertyICalSync;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PropertyAvailability>
 */
class PropertyAvailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PropertyAvailability::class);
    }

    public function hasBlockedOverlap(Property $property, \DateTimeImmutable $start, \DateTimeImmutable $end): bool
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.property = :property')
            ->andWhere('a.isAvailable = false')
            ->andWhere('a.dateStart < :end')
            ->andWhere('a.dateEnd > :start')
            ->setParameter('property', $property)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * @return list<PropertyAvailability>
     */
    public function findBlockedForPeriod(Property $property, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.property = :property')
            ->andWhere('a.isAvailable = false')
            ->andWhere('a.dateStart < :end')
            ->andWhere('a.dateEnd > :start')
            ->setParameter('property', $property)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('a.dateStart', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findImportedEvent(PropertyICalSync $sync, string $externalUid): ?PropertyAvailability
    {
        return $this->findOneBy([
            'iCalSync' => $sync,
            'externalUid' => $externalUid,
            'source' => PropertyAvailability::SOURCE_ICAL,
        ]);
    }

    /**
     * @return list<PropertyAvailability>
     */
    public function findImportedNotSeenSince(PropertyICalSync $sync, \DateTimeImmutable $seenAt): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.iCalSync = :sync')
            ->andWhere('a.source = :source')
            ->andWhere('a.lastSeenAt IS NULL OR a.lastSeenAt < :seenAt')
            ->setParameter('sync', $sync)
            ->setParameter('source', PropertyAvailability::SOURCE_ICAL)
            ->setParameter('seenAt', $seenAt)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<PropertyAvailability>
     */
    public function findImportedBySync(PropertyICalSync $sync): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.iCalSync = :sync')
            ->andWhere('a.source = :source')
            ->setParameter('sync', $sync)
            ->setParameter('source', PropertyAvailability::SOURCE_ICAL)
            ->orderBy('a.dateStart', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
