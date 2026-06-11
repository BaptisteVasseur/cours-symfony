<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ReviewReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReviewReport>
 */
class ReviewReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReviewReport::class);
    }

    /**
     * @return list<ReviewReport>
     */
    public function findAllForListing(): array
    {
        return $this->createQueryBuilder('rr')
            ->addSelect('rev', 'rep', 'reviewer', 'property')
            ->leftJoin('rr.review', 'rev')
            ->leftJoin('rr.reportedBy', 'rep')
            ->leftJoin('rev.reviewer', 'reviewer')
            ->leftJoin('rev.property', 'property')
            ->getQuery()
            ->getResult();
    }

    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('rr')
            ->select('COUNT(rr.id)')
            ->andWhere('rr.status NOT IN (:statuses)')
            ->setParameter('statuses', ['dismissed', 'upheld'])
            ->getQuery()
            ->getSingleScalarResult();
    }
}
