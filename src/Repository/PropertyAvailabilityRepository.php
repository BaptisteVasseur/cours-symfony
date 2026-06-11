<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
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

    public function hasBlockedNight(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): bool
    {
        $count = (int) $this->createQueryBuilder('a')
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

        return $count > 0;
    }

    /**
     * Lignes de disponibilité d'un logement sur un intervalle [start, end],
     * indexées par date (Y-m-d) pour un accès O(1) depuis la grille calendrier.
     *
     * @return array<string, PropertyAvailability>
     */
    public function findForRangeIndexed(Property $property, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $rows = $this->createQueryBuilder('a')
            ->andWhere('a.property = :property')
            ->andWhere('a.availableDate >= :start')
            ->andWhere('a.availableDate <= :end')
            ->setParameter('property', $property)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->getAvailableDate()->format('Y-m-d')] = $row;
        }

        return $indexed;
    }

    /**
     * Supprime les blocages issus d'une source donnée (ex. 'ical:airbnb') sur
     * une fenêtre, avant ré-insertion lors d'une synchronisation iCal. Renvoie
     * le nombre de lignes supprimées.
     */
    public function clearBlocksForSource(Property $property, string $source, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('a')
            ->delete()
            ->andWhere('a.property = :property')
            ->andWhere('a.source = :source')
            ->andWhere('a.availableDate >= :from')
            ->andWhere('a.availableDate <= :to')
            ->setParameter('property', $property)
            ->setParameter('source', $source)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->execute();
    }
}
