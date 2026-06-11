<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PropertyGoogleCalendarSync;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PropertyGoogleCalendarSync>
 */
class PropertyGoogleCalendarSyncRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PropertyGoogleCalendarSync::class);
    }

    /**
     * @return list<PropertyGoogleCalendarSync>
     */
    public function findForSync(): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('property')
            ->innerJoin('s.property', 'property')
            ->andWhere('s.syncEnabled = true')
            ->andWhere('s.accessToken IS NOT NULL')
            ->getQuery()
            ->getResult();
    }
}
