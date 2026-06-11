<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Blockout;
use App\Entity\Property;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Blockout>
 */
class BlockoutRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Blockout::class);
    }

    /**
     * Returns all blockouts for a property overlapping the given month.
     *
     * @return list<Blockout>
     */
    public function findForPropertyInRange(Property $property, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.property = :property')
            ->andWhere('b.startDate <= :to')
            ->andWhere('b.endDate >= :from')
            ->setParameter('property', $property)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('b.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     *
     * @return list<Blockout>
     */
    public function findByPropertyOrderedByDate(Property $property): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.property = :property')
            ->setParameter('property', $property)
            ->orderBy('b.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
