<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\PropertyBlockedPeriod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PropertyBlockedPeriod>
 */
class PropertyBlockedPeriodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PropertyBlockedPeriod::class);
    }

    /**
     * @return list<PropertyBlockedPeriod>
     */
    public function findForPropertyAndMonth(Property $property, int $year, int $month): array
    {
        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $end   = $start->modify('last day of this month');

        return $this->createQueryBuilder('bp')
            ->andWhere('bp.property = :property')
            ->andWhere('bp.startDate <= :end')
            ->andWhere('bp.endDate >= :start')
            ->setParameter('property', $property)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('bp.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<PropertyBlockedPeriod>
     */
    public function findUpcomingForProperty(Property $property): array
    {
        $today = new \DateTimeImmutable('today');

        return $this->createQueryBuilder('bp')
            ->addSelect('cb')
            ->leftJoin('bp.createdBy', 'cb')
            ->andWhere('bp.property = :property')
            ->andWhere('bp.endDate >= :today')
            ->setParameter('property', $property)
            ->setParameter('today', $today)
            ->orderBy('bp.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<PropertyBlockedPeriod>
     */
    public function findAllForProperty(Property $property): array
    {
        return $this->createQueryBuilder('bp')
            ->addSelect('cb')
            ->leftJoin('bp.createdBy', 'cb')
            ->andWhere('bp.property = :property')
            ->setParameter('property', $property)
            ->orderBy('bp.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function hasConflict(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): bool
    {
        // Blocked periods are stored as DATE (no time); strip time component to compare correctly
        $checkinDate  = \DateTimeImmutable::createFromFormat('Y-m-d', $checkin->format('Y-m-d')) ?: $checkin;
        $checkoutDate = \DateTimeImmutable::createFromFormat('Y-m-d', $checkout->format('Y-m-d')) ?: $checkout;

        $count = $this->createQueryBuilder('bp')
            ->select('COUNT(bp.id)')
            ->andWhere('bp.property = :property')
            ->andWhere('bp.startDate < :checkout')
            ->andWhere('bp.endDate >= :checkin')
            ->setParameter('property', $property)
            ->setParameter('checkin', $checkinDate)
            ->setParameter('checkout', $checkoutDate)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
