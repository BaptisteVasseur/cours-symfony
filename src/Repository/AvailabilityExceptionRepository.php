<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AvailabilityException;
use App\Entity\Property;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AvailabilityException>
 */
class AvailabilityExceptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AvailabilityException::class);
    }

    /**
     * Retourne les exceptions dans [from, to[ (checkout exclu selon règle chevauchement).
     *
     * @return AvailabilityException[]
     */
    public function findForPropertyInRange(
        Property $property,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        return $this->createQueryBuilder('e')
            ->where('e.property = :property')
            ->andWhere('e.date >= :from')
            ->andWhere('e.date < :to')
            ->setParameter('property', $property->getId(), 'uuid')
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AvailabilityException[]
     */
    public function findByPropertyAndSource(Property $property, string $source): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.property = :property')
            ->andWhere('e.source = :source')
            ->setParameter('property', $property->getId(), 'uuid')
            ->setParameter('source', $source)
            ->getQuery()
            ->getResult();
    }

    public function deleteByPropertyAndSource(Property $property, string $source): int
    {
        return $this->createQueryBuilder('e')
            ->delete()
            ->where('e.property = :property')
            ->andWhere('e.source = :source')
            ->setParameter('property', $property->getId(), 'uuid')
            ->setParameter('source', $source)
            ->getQuery()
            ->execute();
    }

    public function findOneByPropertyAndDate(Property $property, \DateTimeImmutable $date): ?AvailabilityException
    {
        return $this->createQueryBuilder('e')
            ->where('e.property = :property')
            ->andWhere('e.date = :date')
            ->setParameter('property', $property->getId(), 'uuid')
            ->setParameter('date', $date->format('Y-m-d'))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
