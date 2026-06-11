<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\PropertyUnavailability;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PropertyUnavailability>
 */
class PropertyUnavailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PropertyUnavailability::class);
    }

    /**
     * Find all unavailability periods for a property.
     *
     * @return list<PropertyUnavailability>
     */
    public function findByProperty(Property $property): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.property = :property')
            ->setParameter('property', $property)
            ->orderBy('u.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if there's an unavailability period overlapping with given dates.
     */
    public function hasUnavailabilityBetween(
        Property $property,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): bool {
        $count = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.property = :property')
            ->andWhere('u.startDate < :endDate')
            ->andWhere('u.endDate > :startDate')
            ->setParameter('property', $property)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    /**
     * Find unavailability periods that overlap with given dates.
     *
     * @return list<PropertyUnavailability>
     */
    public function findOverlappingUnavailability(
        Property $property,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        return $this->createQueryBuilder('u')
            ->andWhere('u.property = :property')
            ->andWhere('u.startDate < :endDate')
            ->andWhere('u.endDate > :startDate')
            ->setParameter('property', $property)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('u.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
