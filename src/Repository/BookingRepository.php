<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Listing;
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

    /**
     * Returns confirmed bookings that overlap the given month window.
     *
     * @return Booking[]
     */
    public function findConfirmedForListingAndMonth(Listing $listing, \DateTimeImmutable $firstDay, \DateTimeImmutable $lastDay): array
    {
        $dayAfterLast = $lastDay->modify('+1 day');

        return $this->createQueryBuilder('b')
            ->where('b.listing = :listing')
            ->andWhere('b.status = :status')
            ->andWhere('b.startDate < :dayAfterLast')
            ->andWhere('b.endDate > :firstDay')
            ->setParameter('listing', $listing)
            ->setParameter('status', 'confirmed')
            ->setParameter('dayAfterLast', $dayAfterLast)
            ->setParameter('firstDay', $firstDay)
            ->orderBy('b.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns pending bookings that overlap [checkin, checkout[, excluding a given booking id.
     * Used to auto-cancel conflicts when a booking is confirmed.
     *
     * @return Booking[]
     */
    public function findPendingOverlapping(Listing $listing, \DateTimeInterface $checkin, \DateTimeInterface $checkout, int $excludeId): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.listing = :listing')
            ->andWhere('b.status = :status')
            ->andWhere('b.id != :excludeId')
            ->andWhere('b.startDate < :checkout')
            ->andWhere('b.endDate > :checkin')
            ->setParameter('listing', $listing)
            ->setParameter('status', 'pending')
            ->setParameter('excludeId', $excludeId)
            ->setParameter('checkout', $checkout)
            ->setParameter('checkin', $checkin)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns confirmed bookings that overlap [checkin, checkout[.
     * Used by the availability algorithm.
     *
     * @return Booking[]
     */
    public function findConfirmedOverlapping(Listing $listing, \DateTimeInterface $checkin, \DateTimeInterface $checkout): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.listing = :listing')
            ->andWhere('b.status = :status')
            ->andWhere('b.startDate < :checkout')
            ->andWhere('b.endDate > :checkin')
            ->setParameter('listing', $listing)
            ->setParameter('status', 'confirmed')
            ->setParameter('checkout', $checkout)
            ->setParameter('checkin', $checkin)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns all pending bookings across all listings owned by a host.
     *
     * @return Booking[]
     */
    public function findPendingForHost(User $host): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.listing', 'l')
            ->where('l.host = :host')
            ->andWhere('b.status = :status')
            ->setParameter('host', $host)
            ->setParameter('status', 'pending')
            ->orderBy('b.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns pending bookings created before the given threshold (for expiration).
     *
     * @return Booking[]
     */
    public function findExpiredPending(\DateTimeInterface $threshold): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.status = :status')
            ->andWhere('b.createdAt < :threshold')
            ->setParameter('status', 'pending')
            ->setParameter('threshold', $threshold)
            ->orderBy('b.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns upcoming confirmed bookings for a host (checkout >= today).
     *
     * @return Booking[]
     */
    public function findUpcomingConfirmedForHost(User $host): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.listing', 'l')
            ->where('l.host = :host')
            ->andWhere('b.status = :status')
            ->andWhere('b.endDate >= :today')
            ->setParameter('host', $host)
            ->setParameter('status', 'confirmed')
            ->setParameter('today', new \DateTime('today'))
            ->orderBy('b.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
