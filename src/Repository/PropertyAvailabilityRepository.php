<?php

declare(strict_types=1);

namespace App\Repository;

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

    public function countUnavailableDays(
        string $propertyId,
        \DateTimeImmutable $checkinDate,
        \DateTimeImmutable $checkoutDate,
    ): int {
        
        return (int) $this->createQueryBuilder('availability')
            ->select('COUNT(availability.id)')
            ->andWhere('IDENTITY(availability.property) = :propertyId')
            ->andWhere('availability.isAvailable = false')
            ->andWhere('availability.availableDate >= :checkinDate')
            ->andWhere('availability.availableDate < :checkoutDate')
            ->setParameter('propertyId', $propertyId)
            ->setParameter('checkinDate', $checkinDate)
            ->setParameter('checkoutDate', $checkoutDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findMaximumMinimumStay(
        string $propertyId,
        \DateTimeImmutable $checkinDate,
        \DateTimeImmutable $checkoutDate,
    ): ?int {
        $minimumStay = $this->createQueryBuilder('availability')
            ->select('MAX(availability.minimumStay)')
            ->andWhere('IDENTITY(availability.property) = :propertyId')
            ->andWhere('availability.availableDate >= :checkinDate')
            ->andWhere('availability.availableDate < :checkoutDate')
            ->setParameter('propertyId', $propertyId)
            ->setParameter('checkinDate', $checkinDate)
            ->setParameter('checkoutDate', $checkoutDate)
            ->getQuery()
            ->getSingleScalarResult();

        return $minimumStay !== null ? (int) $minimumStay : null;
    }

    /**
     * @return list<PropertyAvailability>
     */
    public function findBlockedForProperty(string $propertyId): array
    {
        return $this->createQueryBuilder('pa')
            ->andWhere('IDENTITY(pa.property) = :propertyId')
            ->andWhere('pa.isAvailable = false')
            ->orderBy('pa.availableDate', 'ASC')
            ->setParameter('propertyId', $propertyId)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<PropertyAvailability>
     */
    public function findForPriceCalculation(
        string $propertyId,
        \DateTimeImmutable $checkinDate,
        \DateTimeImmutable $checkoutDate,
    ): array {
        return $this->createQueryBuilder('availability')
            ->andWhere('IDENTITY(availability.property) = :propertyId')
            ->andWhere('availability.availableDate >= :checkinDate')
            ->andWhere('availability.availableDate < :checkoutDate')
            ->setParameter('propertyId', $propertyId)
            ->setParameter('checkinDate', $checkinDate)
            ->setParameter('checkoutDate', $checkoutDate)
            ->getQuery()
            ->getResult();
    }
}
