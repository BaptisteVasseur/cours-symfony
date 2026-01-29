<?php

namespace App\Repository;

use App\Entity\Challenge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ChallengeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Challenge::class);
    }

    public function findActiveChallenges(): array
    {
        $now = new \DateTimeImmutable();
        
        return $this->createQueryBuilder('c')
            ->andWhere('c.isActive = true')
            ->andWhere('c.startDate <= :now')
            ->andWhere('c.endDate >= :now')
            ->setParameter('now', $now)
            ->orderBy('c.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
