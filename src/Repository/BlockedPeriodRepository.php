<?php

namespace App\Repository;

use App\Entity\BlockedPeriod;
use App\Entity\Listing;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlockedPeriod>
 */
class BlockedPeriodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlockedPeriod::class);
    }

    /**
     * Returns blocked periods that overlap the given month window.
     * A period overlaps [from, to] when startDate < lastDay+1 AND endDate > firstDay.
     *
     * @return BlockedPeriod[]
     */
    public function findForListingAndMonth(Listing $listing, \DateTimeImmutable $firstDay, \DateTimeImmutable $lastDay): array
    {
        $dayAfterLast = $lastDay->modify('+1 day');

        return $this->createQueryBuilder('bp')
            ->where('bp.listing = :listing')
            ->andWhere('bp.startDate < :dayAfterLast')
            ->andWhere('bp.endDate > :firstDay')
            ->setParameter('listing', $listing)
            ->setParameter('dayAfterLast', $dayAfterLast)
            ->setParameter('firstDay', $firstDay)
            ->orderBy('bp.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns all blocked periods that overlap the given date range [checkin, checkout[.
     * Used by the availability algorithm.
     *
     * @return BlockedPeriod[]
     */
    public function findOverlapping(Listing $listing, \DateTimeInterface $checkin, \DateTimeInterface $checkout): array
    {
        return $this->createQueryBuilder('bp')
            ->where('bp.listing = :listing')
            ->andWhere('bp.startDate < :checkout')
            ->andWhere('bp.endDate > :checkin')
            ->setParameter('listing', $listing)
            ->setParameter('checkout', $checkout)
            ->setParameter('checkin', $checkin)
            ->getQuery()
            ->getResult();
    }
}
