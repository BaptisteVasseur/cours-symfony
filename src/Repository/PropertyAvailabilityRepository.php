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

    public function countUnavailableDays(Property $property, \DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.property = :property')
            ->andWhere('a.availableDate BETWEEN :start AND :end')
            ->andWhere('a.isAvailable = false')
            ->setParameter('property', $property)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<PropertyAvailability> */
    public function findByProperty(Property $property): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.property = :property')
            ->setParameter('property', $property)
            ->orderBy('a.availableDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<PropertyAvailability> */
    public function findUnavailableByProperty(Property $property): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.property = :property')
            ->andWhere('a.isAvailable = false')
            ->setParameter('property', $property)
            ->getQuery()
            ->getResult();
    }
}
