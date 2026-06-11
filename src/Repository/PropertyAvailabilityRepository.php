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
     * @return list<PropertyAvailability>
     */
    public function findByPropertyAndDateRange(
        Property $property,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        return $this->createQueryBuilder('pa')
            ->andWhere('pa.property = :property')
            ->andWhere('pa.availableDate >= :from')
            ->andWhere('pa.availableDate < :to')
            ->setParameter('property', $property)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('pa.availableDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<PropertyAvailability>
     */
    public function findBlockedInRange(
        Property $property,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        return $this->createQueryBuilder('pa')
            ->andWhere('pa.property = :property')
            ->andWhere('pa.isAvailable = false')
            ->andWhere('pa.availableDate >= :from')
            ->andWhere('pa.availableDate < :to')
            ->setParameter('property', $property)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('pa.availableDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByPropertyAndDate(
        Property $property,
        \DateTimeImmutable $date,
    ): ?PropertyAvailability {
        return $this->createQueryBuilder('pa')
            ->andWhere('pa.property = :property')
            ->andWhere('pa.availableDate = :date')
            ->setParameter('property', $property)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function deleteBlockedInRange(
        Property $property,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): void {
        $this->createQueryBuilder('pa')
            ->delete()
            ->andWhere('pa.property = :property')
            ->andWhere('pa.isAvailable = false')
            ->andWhere('pa.availableDate >= :from')
            ->andWhere('pa.availableDate < :to')
            ->setParameter('property', $property)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->execute();
    }
}
