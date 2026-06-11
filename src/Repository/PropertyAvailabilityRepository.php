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
    public function findByPropertyAndMonth(Property $property, int $year, int $month): array
    {
        $start = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month));
        $end = $start->modify('last day of this month');

        return $this->createQueryBuilder('pa')
            ->andWhere('pa.property = :property')
            ->andWhere('pa.availableDate >= :start')
            ->andWhere('pa.availableDate <= :end')
            ->setParameter('property', $property)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('pa.availableDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function hasBlockedDays(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): bool
    {
        $count = $this->createQueryBuilder('pa')
            ->select('COUNT(pa.id)')
            ->andWhere('pa.property = :property')
            ->andWhere('pa.availableDate >= :checkin')
            ->andWhere('pa.availableDate < :checkout')
            ->andWhere('pa.isAvailable = false')
            ->setParameter('property', $property)
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    public function deleteByPropertyAndRange(Property $property, \DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return (int) $this->createQueryBuilder('pa')
            ->delete()
            ->andWhere('pa.property = :property')
            ->andWhere('pa.availableDate >= :start')
            ->andWhere('pa.availableDate <= :end')
            ->setParameter('property', $property)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->execute();
    }
}
