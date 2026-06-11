<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\PropertyICalSync;
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
    public function findForRange(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): array {
        return $this->createQueryBuilder('pa')
            ->andWhere('pa.property = :property')
            ->andWhere('pa.availableDate >= :checkin')
            ->andWhere('pa.availableDate < :checkout')
            ->setParameter('property', $property)
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout)
            ->orderBy('pa.availableDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<PropertyAvailability>
     */
    public function findImportedForSync(PropertyICalSync $sync): array
    {
        return $this->createQueryBuilder('pa')
            ->andWhere('pa.propertyICalSync = :sync')
            ->setParameter('sync', $sync)
            ->orderBy('pa.availableDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
