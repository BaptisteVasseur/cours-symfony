<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * @return list<User>
     */
    public function findForListing(): array
    {
        return $this->createQueryBuilder('u')
            ->addSelect('p')
            ->leftJoin('u.profile', 'p')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForDetail(User $user): ?User
    {
        return $this->createQueryBuilder('u')
            ->addSelect('p', 'prop', 'res', 'doc')
            ->leftJoin('u.profile', 'p')
            ->leftJoin('u.properties', 'prop')
            ->leftJoin('u.reservations', 'res')
            ->leftJoin('u.documents', 'doc')
            ->andWhere('u = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
