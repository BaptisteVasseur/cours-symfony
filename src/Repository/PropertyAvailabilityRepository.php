<?php

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

    public function hasBlockedConflict(\DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut, Property $property): bool
    {
        $count = $this->createQueryBuilder('pa')
            ->select('COUNT(pa.id)')
            ->where('pa.property = :property')
            ->andWhere('pa.startDate < :checkOut AND pa.endDate > :checkIn')
            ->setParameter('property', $property)
            ->setParameter('checkIn', $checkIn)
            ->setParameter('checkOut', $checkOut)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /** @return PropertyAvailability[] */
    public function findForProperty(Property $property): array
    {
        return $this->createQueryBuilder('pa')
            ->where('pa.property = :property')
            ->setParameter('property', $property)
            ->orderBy('pa.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return PropertyAvailability[] */
    public function findFutureForProperty(Property $property): array
    {
        return $this->createQueryBuilder('pa')
            ->where('pa.property = :property')
            ->andWhere('pa.endDate >= :today')
            ->setParameter('property', $property)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('pa.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
