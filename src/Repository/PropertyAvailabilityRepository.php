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

    public function countBlockedDays(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): int
    {
        return (int) $this->createQueryBuilder('pa')
            ->select('COUNT(pa)')
            ->andWhere('pa.property = :property')
            ->andWhere('pa.availableDate >= :checkin')
            ->andWhere('pa.availableDate < :checkout')
            ->andWhere('pa.isAvailable = false')
            ->setParameter('property', $property)
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByDateRange(Property $property, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
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
}