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

    public function countUnavailableDaysBetween(
        Property $property,
        \DateTimeImmutable $checkinDate,
        \DateTimeImmutable $checkoutDate
    ): int {
        return (int) $this->createQueryBuilder('pa')
            ->select('COUNT(pa.id)')
            ->andWhere('pa.property = :property')
            ->andWhere('pa.availableDate >= :checkinDate')
            ->andWhere('pa.availableDate <= :checkoutDate')
            ->andWhere('pa.isAvailable = false')
            ->setParameter('property', $property)
            ->setParameter('checkinDate', $checkinDate)
            ->setParameter('checkoutDate', $checkoutDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // Check availability for a date range and return the unavailable days
    public function findForPropertyBetween(
        Property $property,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        return $this->createQueryBuilder('pa')
            ->andWhere('pa.property = :property')
            ->andWhere('pa.availableDate >= :startDate')
            ->andWhere('pa.availableDate <= :endDate')
            ->setParameter('property', $property)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('pa.availableDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Check availability for a single date
    public function findOneForPropertyAndDate(
        Property $property,
        \DateTimeImmutable $date
    ): ?PropertyAvailability {
        return $this->createQueryBuilder('pa')
            ->andWhere('pa.property = :property')
            ->andWhere('pa.availableDate = :date')
            ->setParameter('property', $property)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
