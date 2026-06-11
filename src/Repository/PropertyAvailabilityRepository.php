<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\User;
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
     * Jours bloqués manuellement par l'hôte sur la plage [checkin, checkout[.
     * Le jour de départ n'est pas occupé : on l'exclut de la plage.
     *
     * @return PropertyAvailability[]
     */
    public function findBlockedDays(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): array {
        return $this->createQueryBuilder('a')
            ->andWhere('a.property = :property')
            ->andWhere('a.isAvailable = false')
            ->andWhere('a.availableDate >= :checkin')
            ->andWhere('a.availableDate < :checkout')
            ->setParameter('property', $property)
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout)
            ->getQuery()
            ->getResult();
    }

    /**
     * Toutes les entrées de calendrier d'un logement sur une plage, indexées par date (Y-m-d).
     *
     * @return array<string, PropertyAvailability>
     */
    public function findInRangeIndexedByDate(
        Property $property,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $rows = $this->createQueryBuilder('a')
            ->andWhere('a.property = :property')
            ->andWhere('a.availableDate >= :from')
            ->andWhere('a.availableDate <= :to')
            ->setParameter('property', $property)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->getAvailableDate()->format('Y-m-d')] = $row;
        }

        return $indexed;
    }

    /**
     * Jours bloqués de TOUS les logements d'un hôte sur la plage [from, to].
     * Sert le calendrier global de l'hôte.
     *
     * @return PropertyAvailability[]
     */
    public function findBlockedForHostInRange(User $host, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.property', 'p')->addSelect('p')
            ->andWhere('p.host = :host')
            ->andWhere('a.isAvailable = false')
            ->andWhere('a.availableDate >= :from')
            ->andWhere('a.availableDate <= :to')
            ->setParameter('host', $host)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }
}
