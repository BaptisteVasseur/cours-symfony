<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PropertyAvailability;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PropertyAvailability>
 */
class PropertyAvailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PropertyAvailability::class);
    }

    /**
     * Vérifie si au moins un jour de la plage [checkin, checkout[ est manuellement bloqué.
     * checkout est exclusif (jour de départ non comptabilisé comme nuitée).
     */
    public function hasBlockedDayInRange(
        \App\Entity\Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): bool {
        $count = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.property = :property')
            ->andWhere('a.isAvailable = false')
            ->andWhere('a.availableDate >= :checkin')
            ->andWhere('a.availableDate < :checkout')
            ->setParameter('property', $property)
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    /**
     * Retourne les enregistrements bloqués (isAvailable = false) dans une plage de dates.
     *
     * @return list<PropertyAvailability>
     */
    public function findBlockedInRange(
        \App\Entity\Property $property,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        return $this->createQueryBuilder('a')
            ->andWhere('a.property = :property')
            ->andWhere('a.isAvailable = false')
            ->andWhere('a.availableDate >= :from')
            ->andWhere('a.availableDate <= :to')
            ->setParameter('property', $property)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('a.availableDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
