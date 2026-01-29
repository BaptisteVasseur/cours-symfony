<?php

namespace App\Repository;

use App\Entity\UserChallenge;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserChallengeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserChallenge::class);
    }

    public function findActiveForUser(User $user): array
    {
        return $this->createQueryBuilder('uc')
            ->innerJoin('uc.challenge', 'c')
            ->andWhere('uc.user = :user')
            ->andWhere('uc.isCompleted = false')
            ->andWhere('c.isActive = true')
            ->andWhere('c.endDate >= :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }
}
