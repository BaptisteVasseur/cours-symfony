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

    public function hasBlockedDays(string $propertyId, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): bool
    {
        $count = (int) $this->createQueryBuilder('pa')
            ->select('COUNT(pa.id)')
            ->andWhere('IDENTITY(pa.property) = :propertyId')
            ->andWhere('pa.availableDate >= :checkin')
            ->andWhere('pa.availableDate < :checkout')
            ->andWhere('pa.isAvailable = false')
            ->setParameter('propertyId', $propertyId)
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * @return list<PropertyAvailability>
     */
    public function findForRange(string $propertyId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('pa')
            ->andWhere('IDENTITY(pa.property) = :propertyId')
            ->andWhere('pa.availableDate >= :from')
            ->andWhere('pa.availableDate <= :to')
            ->setParameter('propertyId', $propertyId)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('pa.availableDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
