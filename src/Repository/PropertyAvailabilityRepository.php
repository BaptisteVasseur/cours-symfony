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
     * @return string[]
     */
    public function findOccupiedDatesByProperty(
        Property $property,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $results = $this->createQueryBuilder('a')
            ->select('a.occupiedDate')
            ->andWhere('a.property = :property')
            ->andWhere('a.occupiedDate >= :from')
            ->andWhere('a.occupiedDate <= :to')
            ->setParameter('property', $property)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        return array_map(
            static fn(array $row): string => $row['occupiedDate']->format('Y-m-d'),
            $results,
        );
    }
}
