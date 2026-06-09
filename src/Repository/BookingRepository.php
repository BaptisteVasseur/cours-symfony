<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    public function findByTraveler(User $user): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.property', 'p')
            ->leftJoin('p.images', 'i')
            ->addSelect('p', 'i')
            ->where('b.traveler = :user')
            ->setParameter('user', $user)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function hasConflict(\DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut, string $propertyId): bool
    {
        $count = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.property = :propertyId')
            ->andWhere('b.status != :cancelled')
            ->andWhere('b.checkIn < :checkOut AND b.checkOut > :checkIn')
            ->setParameter('propertyId', $propertyId)
            ->setParameter('cancelled', \App\Enum\BookingStatus::CANCELLED)
            ->setParameter('checkIn', $checkIn)
            ->setParameter('checkOut', $checkOut)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
