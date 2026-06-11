<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Listing;
use App\Enum\BookingStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    public function hasConfirmedOverlap(
        Listing $listing,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
        ?Booking $exclude = null,
    ): bool {
        $qb = $this->createQueryBuilder('b')
            ->select('1')
            ->andWhere('b.listing = :listing')
            ->andWhere('b.bookingStatus = :confirmed')
            ->andWhere('b.checkIn < :checkOut')
            ->andWhere('b.checkOut > :checkIn')
            ->setParameter('listing', $listing)
            ->setParameter('confirmed', BookingStatus::Confirmed)
            ->setParameter('checkIn', $checkIn->setTime(0, 0))
            ->setParameter('checkOut', $checkOut->setTime(0, 0))
            ->setMaxResults(1);

        if ($exclude !== null && $exclude->getId() !== null) {
            $qb->andWhere('b.id != :exclude')->setParameter('exclude', $exclude->getId());
        }

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }

    public function findConfirmedInWindow(Listing $listing, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.listing = :listing')
            ->andWhere('b.bookingStatus = :confirmed')
            ->andWhere('b.checkIn < :to')
            ->andWhere('b.checkOut > :from')
            ->setParameter('listing', $listing)
            ->setParameter('confirmed', BookingStatus::Confirmed)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(0, 0))
            ->orderBy('b.checkIn', 'ASC')
            ->getQuery()
            ->getResult();
    }


    public function findConfirmedForListing(Listing $listing): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.listing = :listing')
            ->andWhere('b.bookingStatus = :confirmed')
            ->setParameter('listing', $listing)
            ->setParameter('confirmed', BookingStatus::Confirmed)
            ->orderBy('b.checkIn', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingForHost(\App\Entity\User $host): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.listing', 'l')
            ->andWhere('l.host = :host')
            ->andWhere('b.bookingStatus = :pending')
            ->setParameter('host', $host)
            ->setParameter('pending', BookingStatus::Pending)
            ->orderBy('b.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }


    public function findPendingOlderThan(\DateTimeImmutable $threshold): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.bookingStatus = :pending')
            ->andWhere('b.createdAt < :threshold')
            ->setParameter('pending', BookingStatus::Pending)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }

    public function findConfirmedStartingOn(\DateTimeImmutable $day): array
    {
        $start = $day->setTime(0, 0);
        $end = $start->modify('+1 day');

        return $this->createQueryBuilder('b')
            ->andWhere('b.bookingStatus = :confirmed')
            ->andWhere('b.checkIn >= :start')
            ->andWhere('b.checkIn < :end')
            ->setParameter('confirmed', BookingStatus::Confirmed)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
    }


    public function findNeedingCheckinReminder(\DateTimeImmutable $day): array
    {
        $start = $day->setTime(0, 0);
        $end = $start->modify('+1 day');

        return $this->createQueryBuilder('b')
            ->andWhere('b.bookingStatus = :confirmed')
            ->andWhere('b.checkIn >= :start')
            ->andWhere('b.checkIn < :end')
            ->andWhere('b.checkinReminderSentAt IS NULL')
            ->setParameter('confirmed', BookingStatus::Confirmed)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
    }
}
