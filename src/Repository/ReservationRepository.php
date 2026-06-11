<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * @return list<Reservation>
     */
    public function findAllForListing(): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('p', 'm', 'a', 'g', 'gp')
            ->leftJoin('r.property', 'p')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->leftJoin('r.guest', 'g')
            ->leftJoin('g.profile', 'gp')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForDetail(Reservation $reservation): ?Reservation
    {
        return $this->createQueryBuilder('r')
            ->addSelect('p', 'm', 'a', 'g', 'gp', 'host', 'hostProfile', 'h', 'changedBy', 'pay', 'ref', 'inv', 'payo', 'disp', 'openedBy', 'payer')
            ->leftJoin('r.property', 'p')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->leftJoin('r.guest', 'g')
            ->leftJoin('g.profile', 'gp')
            ->leftJoin('p.host', 'host')
            ->leftJoin('host.profile', 'hostProfile')
            ->leftJoin('r.statusHistory', 'h')
            ->leftJoin('h.changedBy', 'changedBy')
            ->leftJoin('r.payments', 'pay')
            ->leftJoin('pay.refunds', 'ref')
            ->leftJoin('pay.payer', 'payer')
            ->leftJoin('r.invoice', 'inv')
            ->leftJoin('r.payouts', 'payo')
            ->leftJoin('r.disputes', 'disp')
            ->leftJoin('disp.openedBy', 'openedBy')
            ->andWhere('r = :reservation')
            ->setParameter('reservation', $reservation)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function sumCompletedRevenue(): float
    {
        $result = $this->createQueryBuilder('r')
            ->select('SUM(r.totalPrice)')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('statuses', ['confirmed', 'completed'])
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (float) $result : 0.0;
    }

    /**
     * Check if a guest already has a confirmed reservation overlapping the requested dates (any property).
     * Prevents a user from booking two overlapping stays simultaneously.
     */
    public function countGuestOverlaps(
        \App\Entity\User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        ?Reservation $exclude = null,
    ): int {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.guest = :guest')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.checkinDate < :checkout')
            ->andWhere('r.checkoutDate > :checkin')
            ->setParameter('guest', $guest)
            ->setParameter('statuses', ['confirmed', 'pending'])
            ->setParameter('checkout', $checkout)
            ->setParameter('checkin', $checkin);

        if ($exclude !== null) {
            $qb->andWhere('r != :exclude')->setParameter('exclude', $exclude);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Return all confirmed/completed reservations for a property in the future (for calendar display).
     *
     * @return list<array{checkin: \DateTimeImmutable, checkout: \DateTimeImmutable}>
     */
    public function findBlockedRangesForProperty(Property $property): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.checkinDate AS checkin, r.checkoutDate AS checkout')
            ->andWhere('r.property = :property')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.checkoutDate > :now')
            ->setParameter('property', $property)
            ->setParameter('statuses', ['confirmed', 'completed'])
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('r.checkinDate', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static fn (array $r): array => [
            'checkin'  => $r['checkin'],
            'checkout' => $r['checkout'],
        ], $rows);
    }

    /**
     * Count confirmed reservations that conflict with the given date range, accounting for 3h gap rule.
     * Two reservations conflict when checkin < other.checkout + 3h AND checkout > other.checkin - 3h.
     */
    public function countConfirmedConflicts(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        ?Reservation $exclude = null,
    ): int {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.property = :property')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.checkinDate < :checkoutPlus3h')
            ->andWhere('r.checkoutDate > :checkinMinus3h')
            ->setParameter('property', $property)
            ->setParameter('statuses', ['confirmed', 'completed'])
            ->setParameter('checkoutPlus3h', $checkout->modify('+3 hours'))
            ->setParameter('checkinMinus3h', $checkin->modify('-3 hours'));

        if ($exclude !== null) {
            $qb->andWhere('r != :exclude')->setParameter('exclude', $exclude);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Count pending reservations that overlap with the given date range.
     * Pending reservations don't block but max 3 can overlap on the same range.
     */
    public function countPendingOverlaps(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        ?Reservation $exclude = null,
    ): int {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.property = :property')
            ->andWhere('r.status = :status')
            ->andWhere('r.checkinDate < :checkout')
            ->andWhere('r.checkoutDate > :checkin')
            ->setParameter('property', $property)
            ->setParameter('status', 'pending')
            ->setParameter('checkout', $checkout)
            ->setParameter('checkin', $checkin);

        if ($exclude !== null) {
            $qb->andWhere('r != :exclude')->setParameter('exclude', $exclude);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<Reservation>
     */
    public function findPendingExpired(): array
    {
        $expiry = new \DateTimeImmutable('-24 hours');

        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.createdAt < :expiry')
            ->setParameter('status', 'pending')
            ->setParameter('expiry', $expiry)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Reservation>
     */
    public function findByGuestForListing(User $guest): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('p', 'm', 'a', 'g', 'gp')
            ->leftJoin('r.property', 'p')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->leftJoin('r.guest', 'g')
            ->leftJoin('g.profile', 'gp')
            ->andWhere('r.guest = :guest')
            ->setParameter('guest', $guest)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * All reservations on properties owned by $host, newest first.
     *
     * @return list<Reservation>
     */
    public function findByHostForListing(User $host): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('p', 'm', 'a', 'g', 'gp')
            ->leftJoin('r.property', 'p')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->leftJoin('r.guest', 'g')
            ->leftJoin('g.profile', 'gp')
            ->andWhere('p.host = :host')
            ->setParameter('host', $host)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
