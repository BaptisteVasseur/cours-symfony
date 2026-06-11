<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AvailabilityBlock;
use App\Entity\Listing;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AvailabilityBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AvailabilityBlock::class);
    }

    public function hasBlockOverlap(Listing $listing, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool
    {
        return $this->createQueryBuilder('a')
            ->select('1')
            ->andWhere('a.listing = :listing')
            ->andWhere('a.startDate < :checkOut')
            ->andWhere('a.endDate > :checkIn')
            ->setParameter('listing', $listing)
            ->setParameter('checkIn', $checkIn->setTime(0, 0))
            ->setParameter('checkOut', $checkOut->setTime(0, 0))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }

    public function findInWindow(Listing $listing, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.listing = :listing')
            ->andWhere('a.startDate < :to')
            ->andWhere('a.endDate > :from')
            ->setParameter('listing', $listing)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(0, 0))
            ->orderBy('a.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findImportedIndexedByUid(Listing $listing): array
    {
        $blocks = $this->createQueryBuilder('a')
            ->andWhere('a.listing = :listing')
            ->andWhere('a.source = :ical')
            ->setParameter('listing', $listing)
            ->setParameter('ical', AvailabilityBlock::SOURCE_ICAL)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($blocks as $block) {
            if ($block->getExternalUid() !== null) {
                $indexed[$block->getExternalUid()] = $block;
            }
        }

        return $indexed;
    }
}
