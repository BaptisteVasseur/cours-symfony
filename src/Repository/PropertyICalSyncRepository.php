<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PropertyICalSync;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

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
    public function findAllWithProperty(?string $propertyId = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->addSelect('p')
            ->innerJoin('s.property', 'p');

        if ($propertyId !== null && $propertyId !== '') {
            $qb->andWhere('p.id = :propertyId')
                ->setParameter('propertyId', Uuid::fromString($propertyId));
        }

        return $qb->getQuery()->getResult();
    }
}
