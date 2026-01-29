<?php

namespace App\Repository;

use App\Entity\Availability;
use App\Entity\Property;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AvailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Availability::class);
    }

    public function findByPropertyAndDateRange(Property $property, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.property = :property')
            ->andWhere('a.date >= :startDate')
            ->andWhere('a.date <= :endDate')
            ->setParameter('property', $property)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('a.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
