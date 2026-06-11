<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\PropertyUnavailability;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PropertyUnavailability>
 */
class PropertyUnavailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PropertyUnavailability::class);
    }

    /**
     * @return PropertyUnavailability[]
     */
    public function findOverlapping(string $propertyId, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.property = :propertyId')
            ->andWhere('u.startDate < :end')
            ->andWhere('u.endDate > :start')
            ->setParameter('propertyId', $propertyId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PropertyUnavailability[]
     */
    public function findByProperty(Property $property): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.property = :property')
            ->setParameter('property', $property)
            ->orderBy('u.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
