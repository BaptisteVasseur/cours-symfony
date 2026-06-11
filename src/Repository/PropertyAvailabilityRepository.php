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

    public function hasBlockedDay(
        Property $property,
        \DateTimeImmutable $checkinDate,
        \DateTimeImmutable $checkoutDate,
    ): bool {
        $result = $this->createQueryBuilder('a')
            ->select('a.id')
            ->andWhere('a.property = :property')
            ->andWhere('a.isAvailable = false')
            ->andWhere('a.availableDate >= :checkin')
            ->andWhere('a.availableDate < :checkout')
            ->setParameter('property', $property)
            ->setParameter('checkin', $checkinDate)
            ->setParameter('checkout', $checkoutDate)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result !== null;
    }
}
