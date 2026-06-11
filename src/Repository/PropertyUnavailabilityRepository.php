<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\PropertyUnavailability;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PropertyUnavailability>
 */
class PropertyUnavailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PropertyUnavailability::class);
    }

    /**
     * La plage [checkin, checkout) recoupe-t-elle une période bloquée par l'hôte ?
     * Chevauchement : (u.startDate < :checkout) ET (u.endDate > :checkin).
     */
    public function hasOverlap(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): bool {
        $count = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.property = :property')
            ->andWhere('u.startDate < :checkout')
            ->andWhere('u.endDate > :checkin')
            ->setParameter('property', $property)
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Périodes bloquées chevauchant la fenêtre [from, to) (pour l'affichage calendrier).
     *
     * @return list<PropertyUnavailability>
     */
    public function findOverlapping(
        Property $property,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        return $this->createQueryBuilder('u')
            ->andWhere('u.property = :property')
            ->andWhere('u.startDate < :to')
            ->andWhere('u.endDate > :from')
            ->setParameter('property', $property)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('u.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
