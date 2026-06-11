<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\Unavailability;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Unavailability>
 */
class UnavailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Unavailability::class);
    }

    /**
     * @return list<Unavailability>
     */
    public function findOverlapping(
        Property $property,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?Unavailability $exclude = null,
    ): array {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.property = :property')
            ->andWhere('u.startDate < :end')
            ->andWhere('u.endDate > :start')
            ->setParameter('property', $property)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($exclude !== null) {
            $qb->andWhere('u != :exclude')->setParameter('exclude', $exclude);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<Unavailability>
     */
    public function findForPropertyBetween(
        Property $property,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): array {
        return $this->createQueryBuilder('u')
            ->andWhere('u.property = :property')
            ->andWhere('u.startDate < :end')
            ->andWhere('u.endDate > :start')
            ->setParameter('property', $property)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('u.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
