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

    public function findOneByPropertyAndDate(Property $property, \DateTimeImmutable $date): ?PropertyAvailability
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.property = :property')
            ->andWhere('a.availableDate = :date')
            ->setParameter('property', $property)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<PropertyAvailability> */
    public function findBlockedInPeriod(Property $property, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.property = :property')
            ->andWhere('a.isAvailable = false')
            ->andWhere('a.availableDate >= :checkIn')
            ->andWhere('a.availableDate < :checkOut')
            ->setParameter('property', $property)
            ->setParameter('checkIn', $checkIn)
            ->setParameter('checkOut', $checkOut)
            ->getQuery()
            ->getResult();
    }

    public function getMaxMinimumStayInPeriod(Property $property, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): ?int
    {
        $result = $this->createQueryBuilder('a')
            ->select('MAX(a.minimumStay)')
            ->andWhere('a.property = :property')
            ->andWhere('a.availableDate >= :checkIn')
            ->andWhere('a.availableDate < :checkOut')
            ->andWhere('a.minimumStay IS NOT NULL')
            ->setParameter('property', $property)
            ->setParameter('checkIn', $checkIn)
            ->setParameter('checkOut', $checkOut)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (int) $result : null;
    }

    /**
     * Retourne un tableau indexé Y-m-d => priceOverride (float) pour les jours
     * ayant un tarif spécial dans la période [checkIn, checkOut[.
     * @return array<string, float>
     */
    public function getPriceOverridesInPeriod(Property $property, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.availableDate', 'a.priceOverride')
            ->andWhere('a.property = :property')
            ->andWhere('a.isAvailable != false')
            ->andWhere('a.priceOverride IS NOT NULL')
            ->andWhere('a.availableDate >= :checkIn')
            ->andWhere('a.availableDate < :checkOut')
            ->setParameter('property', $property)
            ->setParameter('checkIn', $checkIn)
            ->setParameter('checkOut', $checkOut)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $key = $row['availableDate'] instanceof \DateTimeInterface
                ? $row['availableDate']->format('Y-m-d')
                : (string) $row['availableDate'];
            $map[$key] = (float) $row['priceOverride'];
        }

        return $map;
    }

    /** @return array<\DateTimeImmutable> Dates bloquées pour affichage calendrier */
    public function findBlockedDatesByProperty(Property $property, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.availableDate')
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

        return array_column($rows, 'availableDate');
    }

    /** @return list<PropertyAvailability> Rows bloquées avec motif pour le calendrier hôte */
    public function findBlockedRowsByProperty(Property $property, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
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
