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

    public function countBlockedInRange(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): int {
        return (int) $this->createQueryBuilder('pa')
            ->select('COUNT(pa.id)')
            ->andWhere('pa.property = :property')
            ->andWhere('pa.isAvailable = false')
            ->andWhere('pa.availableDate >= :checkin')
            ->andWhere('pa.availableDate < :checkout')
            ->setParameter('property', $property)
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<PropertyAvailability>
     */
    public function findForMonth(Property $property, int $year, int $month): array
    {
        $start = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month));
        $end = $start->modify('first day of next month');

        return $this->createQueryBuilder('pa')
            ->andWhere('pa.property = :property')
            ->andWhere('pa.availableDate >= :start')
            ->andWhere('pa.availableDate < :end')
            ->setParameter('property', $property)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('pa.availableDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByPropertyAndDate(Property $property, \DateTimeImmutable $date): ?PropertyAvailability
    {
        return $this->createQueryBuilder('pa')
            ->andWhere('pa.property = :property')
            ->andWhere('pa.availableDate = :date')
            ->setParameter('property', $property)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function deleteFutureBySource(Property $property, string $source): int
    {
        return (int) $this->createQueryBuilder('pa')
            ->delete()
            ->andWhere('pa.property = :property')
            ->andWhere('pa.icalSyncSource = :source')
            ->andWhere('pa.availableDate >= :today')
            ->setParameter('property', $property)
            ->setParameter('source', $source)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()
            ->execute();
    }
}

