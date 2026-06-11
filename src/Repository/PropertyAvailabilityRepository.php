<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
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
    public function findOverlappingBlocks(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.property = :property')
            ->andWhere('a.startDate < :checkout')
            ->andWhere('a.endDate > :checkin')
            ->setParameter('property', $property)
            ->setParameter('checkin', $checkin, Types::DATE_IMMUTABLE)
            ->setParameter('checkout', $checkout, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<PropertyAvailability>
     */
    public function findByPropertyOrdered(Property $property): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.property = :property')
            ->setParameter('property', $property)
            ->orderBy('a.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
