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

    /**
     * @return PropertyAvailability[]
     */
    public function findBlockedDates(Property $property): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.property = :property')
            ->andWhere('a.isAvailable = false')
            ->andWhere('a.availableDate >= :today')
            ->setParameter('property', $property)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('a.availableDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PropertyAvailability[]
     */
    public function findBlockedInRange(Property $property, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.property = :property')
            ->andWhere('a.isAvailable = false')
            ->andWhere('a.availableDate BETWEEN :start AND :end')
            ->setParameter('property', $property)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns true if any day in the range is manually blocked by the host.
     */
    public function hasBlockedDayInRange(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): bool
    {
        $count = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.property = :property')
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
     * @return array<string, string> dates bloquées pour le calendrier frontend ['Y-m-d' => reason]
     */
    public function findBlockedRangesForCalendar(Property $property): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.availableDate', 'a.reason')
            ->where('a.property = :property')
            ->andWhere('a.isAvailable = false')
            ->andWhere('a.availableDate >= :today')
            ->setParameter('property', $property)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('a.availableDate', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['availableDate']->format('Y-m-d')] = $row['reason'] ?? '';
        }

        return $result;
    }
}
