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
    public function findForSync(?string $propertyId = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->addSelect('p')
            ->leftJoin('s.property', 'p');

        if ($propertyId !== null) {
            $qb->andWhere('p.id = :propertyId')->setParameter('propertyId', $propertyId);
        }

        return $qb->getQuery()->getResult();
    }
}
