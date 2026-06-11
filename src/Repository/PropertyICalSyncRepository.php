<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\PropertyICalSync;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PropertyICalSync>
 */
class PropertyICalSyncRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PropertyICalSync::class);
    }

    /**
     * @return list<PropertyICalSync>
     */
    public function findForProperty(Property $property): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.property = :property')
            ->setParameter('property', $property)
            ->orderBy('s.providerName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForProperty(Property $property, string $id): ?PropertyICalSync
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.property = :property')
            ->andWhere('s.id = :id')
            ->setParameter('property', $property)
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
