<?php

namespace App\Repository;

use App\Entity\UserReward;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRewardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserReward::class);
    }

    public function findAvailableForUser(User $user): array
    {
        return $this->createQueryBuilder('ur')
            ->andWhere('ur.user = :user')
            ->andWhere('ur.status = :status')
            ->andWhere('ur.expiresAt IS NULL OR ur.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('status', 'available')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }
}
