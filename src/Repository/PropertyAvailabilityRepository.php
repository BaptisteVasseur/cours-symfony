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
     * Returns all blocked days (isAvailable = false) for a given property within a date range.
     *
     * @return list<PropertyAvailability>
     */
    public function findBlockedInRange(Property $property, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('pa')
            ->andWhere('pa.property = :property')
            ->andWhere('pa.availableDate >= :from')
            ->andWhere('pa.availableDate < :to')
            ->andWhere('pa.isAvailable = false')
            ->setParameter('property', $property)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('pa.availableDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns all availability records for a given property and month.
     *
     * @return list<PropertyAvailability>
     */
    public function findByPropertyAndMonth(Property $property, int $year, int $month): array
    {
        $from = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $to = $from->modify('first day of next month');

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
}
