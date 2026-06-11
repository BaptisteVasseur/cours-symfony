<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
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

    public function hasBlockedDateInRange(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): bool {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.property = :property')
            ->andWhere('a.isAvailable = false')
            ->andWhere('a.availableDate >= :checkin')
            ->andWhere('a.availableDate < :checkout')
            ->setParameter('property', $property)
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * @return list<PropertyAvailability>
     */
    public function findBlockedInRange(
        Property $property,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        return $this->createQueryBuilder('a')
            ->andWhere('a.property = :property')
            ->andWhere('a.isAvailable = false')
            ->andWhere('a.availableDate >= :from')
            ->andWhere('a.availableDate <= :to')
            ->setParameter('property', $property)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('a.availableDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<PropertyAvailability>
     */
    public function findManualInRange(
        Property $property,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        return $this->createQueryBuilder('a')
            ->andWhere('a.property = :property')
            ->andWhere('a.source = :source')
            ->andWhere('a.availableDate >= :from')
            ->andWhere('a.availableDate <= :to')
            ->setParameter('property', $property)
            ->setParameter('source', 'manual')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<PropertyAvailability>
     */
    public function findImportedForProperty(Property $property): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.property = :property')
            ->andWhere('a.source = :source')
            ->setParameter('property', $property)
            ->setParameter('source', 'ical_import')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<PropertyAvailability>
     */
    public function findBySourceUid(Property $property, string $sourceUid): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.property = :property')
            ->andWhere('a.sourceUid = :uid')
            ->setParameter('property', $property)
            ->setParameter('uid', $sourceUid)
            ->getQuery()
            ->getResult();
    }
}
