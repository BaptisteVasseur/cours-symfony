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

    public function hasBlockedDayInRange(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): bool {
        $count = $this->createQueryBuilder('pa')
            ->select('COUNT(pa.id)')
            ->andWhere('pa.property = :property')
            ->andWhere('pa.blockedDate >= :checkin')
            ->andWhere('pa.blockedDate < :checkout')
            ->setParameter('property', $property)
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * @return list<\DateTimeImmutable>
     */
    public function findBlockedDatesForMonth(Property $property, int $year, int $month): array
    {
        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $end = $start->modify('first day of next month');

        $rows = $this->createQueryBuilder('pa')
            ->select('pa.blockedDate')
            ->andWhere('pa.property = :property')
            ->andWhere('pa.blockedDate >= :start')
            ->andWhere('pa.blockedDate < :end')
            ->setParameter('property', $property)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();

        return array_column($rows, 'blockedDate');
    }
}
