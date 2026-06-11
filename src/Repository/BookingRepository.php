<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\User;
use App\Enum\BookingStatus;
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

    public function findAllWithRelations(): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.property', 'p')->addSelect('p')
            ->leftJoin('b.traveler', 't')->addSelect('t')
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findRecentWithRelations(int $limit): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.property', 'p')->addSelect('p')
            ->leftJoin('b.traveler', 't')->addSelect('t')
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByStatusWithRelations(BookingStatus $status): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.property', 'p')->addSelect('p')
            ->leftJoin('b.traveler', 't')->addSelect('t')
            ->where('b.status = :status')
            ->setParameter('status', $status)
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
            ->setParameter('cancelled', BookingStatus::CANCELLED)
            ->setParameter('checkIn', $checkIn)
            ->setParameter('checkOut', $checkOut)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function hasConfirmedConflict(\DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut, \App\Entity\Property $property): bool
    {
        $count = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.property = :property')
            ->andWhere('b.status = :confirmed')
            ->andWhere('b.checkIn < :checkOut AND b.checkOut > :checkIn')
            ->setParameter('property', $property)
            ->setParameter('confirmed', BookingStatus::CONFIRMED)
            ->setParameter('checkIn', $checkIn)
            ->setParameter('checkOut', $checkOut)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /** @return Booking[] pending bookings for properties owned by a given host */
    public function findPendingForHost(\App\Entity\User $host): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.property', 'p')->addSelect('p')
            ->leftJoin('b.traveler', 't')->addSelect('t')
            ->where('p.host = :host')
            ->andWhere('b.status = :pending')
            ->setParameter('host', $host)
            ->setParameter('pending', BookingStatus::PENDING)
            ->orderBy('b.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Booking[] all bookings for properties owned by a given host */
    public function findForHost(\App\Entity\User $host): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.property', 'p')->addSelect('p')
            ->leftJoin('b.traveler', 't')->addSelect('t')
            ->where('p.host = :host')
            ->setParameter('host', $host)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Booking[] confirmed bookings for a specific property (for iCal / calendar) */
    public function findConfirmedForProperty(\App\Entity\Property $property): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.traveler', 't')->addSelect('t')
            ->where('b.property = :property')
            ->andWhere('b.status = :confirmed')
            ->setParameter('property', $property)
            ->setParameter('confirmed', BookingStatus::CONFIRMED)
            ->orderBy('b.checkIn', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Booking[] pending bookings older than 24h (for auto-expiration) */
    public function findExpiredPending(): array
    {
        $limit = new \DateTimeImmutable('-24 hours');

        return $this->createQueryBuilder('b')
            ->where('b.status = :pending')
            ->andWhere('b.createdAt < :limit')
            ->setParameter('pending', BookingStatus::PENDING)
            ->setParameter('limit', $limit)
            ->getQuery()
            ->getResult();
    }
}
