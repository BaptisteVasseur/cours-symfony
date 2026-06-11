<?php

declare(strict_types=1);

namespace App\Repository;

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
    public function findByPropertyId(string $propertyId): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.property', 'p')
            ->andWhere('p.id = :id')
            ->setParameter('id', $propertyId)
            ->getQuery()
            ->getResult();
    }
}
