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
        return $this->findOneBy([
            'property' => $property,
            'availableDate' => $date,
        ]);
    }

    public function getMinimumStayForCheckin(Property $property, \DateTimeImmutable $checkin): ?int
    {
        $record = $this->findOneByPropertyAndDate($property, $checkin);

        return $record?->getMinimumStay();
    }

    /**
     * @return array<string, string>
     */
    public function findPriceOverridesInRange(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): array {
        $records = $this->createQueryBuilder('a')
            ->andWhere('a.property = :property')
            ->andWhere('a.priceOverride IS NOT NULL')
            ->andWhere('a.availableDate >= :checkin')
            ->andWhere('a.availableDate < :checkout')
            ->setParameter('property', $property)
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout)
            ->getQuery()
            ->getResult();

        $overrides = [];
        foreach ($records as $record) {
            $overrides[$record->getAvailableDate()->format('Y-m-d')] = (string) $record->getPriceOverride();
        }

        return $overrides;
    }

    public function countBlockedDaysInRange(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): int {
        return (int) $this->createQueryBuilder('a')
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
    }

    /**
     * @return list<PropertyAvailability>
     */
    public function findForMonth(Property $property, int $year, int $month): array
    {
        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $end = $start->modify('first day of next month');

        return $this->createQueryBuilder('a')
            ->andWhere('a.property = :property')
            ->andWhere('a.availableDate >= :start')
            ->andWhere('a.availableDate < :end')
            ->setParameter('property', $property)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('a.availableDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<PropertyAvailability>
     */
    public function findOneByPropertyAndExternalUid(Property $property, string $externalUid): ?PropertyAvailability
    {
        return $this->findOneBy([
            'property' => $property,
            'externalUid' => $externalUid,
        ]);
    }

    /**
     * @return list<PropertyAvailability>
     */
    public function findIcalBlocksByProperty(Property $property): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.property = :property')
            ->andWhere('a.source = :source')
            ->andWhere('a.isAvailable = false')
            ->setParameter('property', $property)
            ->setParameter('source', 'ical')
            ->getQuery()
            ->getResult();
    }

    public function findBlockedInRange(
        Property $property,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
    ): array {
        return $this->createQueryBuilder('a')
            ->andWhere('a.property = :property')
            ->andWhere('a.isAvailable = false')
            ->andWhere('a.availableDate >= :startDate')
            ->andWhere('a.availableDate < :endDate')
            ->setParameter('property', $property)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('a.availableDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
