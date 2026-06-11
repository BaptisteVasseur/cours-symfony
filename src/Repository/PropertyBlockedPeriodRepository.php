<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\PropertyBlockedPeriod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PropertyBlockedPeriod>
 */
class PropertyBlockedPeriodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PropertyBlockedPeriod::class);
    }

    /**
     * Périodes bloquées qui chevauchent la fenêtre [from, to) — overlap :
     * startAt < to AND endAt > from. Une seule requête pour toute la grille.
     *
     * @return list<PropertyBlockedPeriod>
     */
    public function findOverlapping(Property $property, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.property = :property')
            ->andWhere('b.startAt < :to')
            ->andWhere('b.endAt > :from')
            ->setParameter('property', $property)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('b.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Périodes bloquées encore en cours ou à venir (fin après l'instant donné).
     *
     * @return list<PropertyBlockedPeriod>
     */
    public function findUpcoming(Property $property, \DateTimeImmutable $after): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.property = :property')
            ->andWhere('b.endAt > :after')
            ->setParameter('property', $property)
            ->setParameter('after', $after)
            ->orderBy('b.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
