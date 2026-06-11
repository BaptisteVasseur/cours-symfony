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
    public function findForPropertyMonth(Property $property, int $year, int $month): array
    {
        $from = new \DateTimeImmutable("$year-$month-01");
        $to   = $from->modify('last day of this month');

        return $this->createQueryBuilder('pa')
            ->andWhere('pa.property = :property')
            ->andWhere('pa.availableDate >= :from')
            ->andWhere('pa.availableDate <= :to')
            ->setParameter('property', $property)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('pa.availableDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns true if any day in [checkin, checkout) is manually blocked by the host.
     * Uses a single COUNT query instead of iterating day by day.
     */
    public function hasBlockedDays(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): bool {
        $lastNight = $checkout->modify('-1 day');

        return (int) $this->createQueryBuilder('pa')
            ->select('COUNT(pa.id)')
            ->andWhere('pa.property = :property')
            ->andWhere('pa.availableDate >= :checkin')
            ->andWhere('pa.availableDate <= :lastNight')
            ->andWhere('pa.isAvailable = false')
            ->setParameter('property', $property)
            ->setParameter('checkin', $checkin)
            ->setParameter('lastNight', $lastNight)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
