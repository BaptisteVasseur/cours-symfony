<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AvailabilitySchedule;
use App\Entity\Property;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AvailabilitySchedule>
 */
class AvailabilityScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AvailabilitySchedule::class);
    }

    /**
     * Retourne les schedules actifs pour un logement couvrant au moins partiellement la période [checkin, checkout].
     *
     * @return AvailabilitySchedule[]
     */
    public function findActiveForProperty(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): array {
        return $this->createQueryBuilder('s')
            ->where('s.property = :property')
            ->andWhere('s.startDate <= :checkout')
            ->andWhere('s.endDate >= :checkin')
            ->setParameter('property', $property->getId(), 'uuid')
            ->setParameter('checkin', $checkin->format('Y-m-d'))
            ->setParameter('checkout', $checkout->format('Y-m-d'))
            ->orderBy('s.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
