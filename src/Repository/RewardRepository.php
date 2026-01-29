<?php

namespace App\Repository;

use App\Entity\Reward;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RewardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reward::class);
    }

    public function findActiveRewards(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.isActive = true')
            ->orderBy('r.pointsRequired', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
