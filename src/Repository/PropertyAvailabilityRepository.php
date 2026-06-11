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

    /**
     * Count host-blocked days within the half-open range [checkin, checkout).
     * Single set-based query — independent of the number of nights.
     */
    public function countBlockedInRange(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): int {
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
            ->getSingleScalarResult();
    }

    /**
     * Availability rows for a property within the half-open range [from, to), ordered by date.
     *
     * @return list<PropertyAvailability>
     */
    public function findInRange(
        Property $property,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        return $this->createQueryBuilder('a')
            ->andWhere('a.property = :property')
            ->andWhere('a.availableDate >= :from')
            ->andWhere('a.availableDate < :to')
            ->setParameter('property', $property)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('a.availableDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * All availability rows of a property created by a given import source (e.g. 'import:airbnb').
     * Used by the iCal importer to reconcile previously-imported blocks without touching manual ones.
     *
     * @return list<PropertyAvailability>
     */
    public function findBySource(Property $property, string $source): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.property = :property')
            ->andWhere('a.source = :source')
            ->setParameter('property', $property)
            ->setParameter('source', $source)
            ->getQuery()
            ->getResult();
    }
}
