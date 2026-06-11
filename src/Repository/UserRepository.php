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
    public function findForListing(
        ?string $search = null,
        string $sort = 'createdAt',
        string $dir = 'DESC',
    ): array {
        $allowedSorts = ['email' => 'u.email', 'status' => 'u.status', 'createdAt' => 'u.createdAt'];
        $orderCol = $allowedSorts[$sort] ?? 'u.createdAt';
        $orderDir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('u')
            ->addSelect('p')
            ->leftJoin('u.profile', 'p')
            ->orderBy($orderCol, $orderDir);

        if ($search !== null && $search !== '') {
            $qb->andWhere('u.email LIKE :search OR p.firstName LIKE :search OR p.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
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
