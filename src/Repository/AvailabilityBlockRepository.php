<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AvailabilityBlock;
use App\Entity\Property;
use App\Enum\BlockReason;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AvailabilityBlock>
 */
class AvailabilityBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AvailabilityBlock::class);
    }

    public function countOverlapping(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): int {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.property = :property')
            ->andWhere('b.startDate < :checkout')
            ->andWhere('b.endDate > :checkin')
            ->setParameter('property', $property)
            ->setParameter('checkout', $checkout)
            ->setParameter('checkin', $checkin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<AvailabilityBlock>
     */
    public function findOverlapping(
        Property $property,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): array {
        return $this->createQueryBuilder('b')
            ->andWhere('b.property = :property')
            ->andWhere('b.startDate < :end')
            ->andWhere('b.endDate > :start')
            ->setParameter('property', $property)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('b.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneImportedByUid(Property $property, string $uid): ?AvailabilityBlock
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.property = :property')
            ->andWhere('b.reason = :reason')
            ->andWhere('b.externalUid = :uid')
            ->setParameter('property', $property)
            ->setParameter('reason', BlockReason::ICAL_IMPORT)
            ->setParameter('uid', $uid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<AvailabilityBlock>
     */
    public function findImportedByProperty(Property $property): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.property = :property')
            ->andWhere('b.reason = :reason')
            ->setParameter('property', $property)
            ->setParameter('reason', BlockReason::ICAL_IMPORT)
            ->getQuery()
            ->getResult();
    }
}
