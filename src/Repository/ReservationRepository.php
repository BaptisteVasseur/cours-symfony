<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Reservation;
use App\Entity\Property;
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

    public function countOverlappingConfirmed(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): int {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.property = :property')
            ->andWhere('r.status = :status')
            ->andWhere('r.checkinDate < :checkout')
            ->andWhere('r.checkoutDate > :checkin')
            ->setParameter('property', $property)
            ->setParameter('status', 'confirmed')
            ->setParameter('checkout', $checkout)
            ->setParameter('checkin', $checkin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Reservation>
     */
    public function findOverlappingConfirmed(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): array {
        return $this->createQueryBuilder('r')
            ->addSelect('g', 'gp')
            ->leftJoin('r.guest', 'g')
            ->leftJoin('g.profile', 'gp')
            ->andWhere('r.property = :property')
            ->andWhere('r.status = :status')
            ->andWhere('r.checkinDate < :checkout')
            ->andWhere('r.checkoutDate > :checkin')
            ->setParameter('property', $property)
            ->setParameter('status', 'confirmed')
            ->setParameter('checkout', $checkout)
            ->setParameter('checkin', $checkin)
            ->orderBy('r.checkinDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Reservation>
     */
    public function findConfirmedForIcal(Property $property): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('g', 'gp')
            ->leftJoin('r.guest', 'g')
            ->leftJoin('g.profile', 'gp')
            ->andWhere('r.property = :property')
            ->andWhere('r.status = :status')
            ->setParameter('property', $property)
            ->setParameter('status', 'confirmed')
            ->orderBy('r.checkinDate', 'ASC')
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
     * @return list<Reservation>
     */
    public function findByHostForListing(User $host, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->addSelect('p', 'm', 'a', 'g', 'gp')
            ->leftJoin('r.property', 'p')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->leftJoin('r.guest', 'g')
            ->leftJoin('g.profile', 'gp')
            ->andWhere('p.host = :host')
            ->setParameter('host', $host);

        if ($status !== null && $status !== '') {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function sumConfirmedRevenueByHost(User $host): float
    {
        $result = $this->createQueryBuilder('r')
            ->select('SUM(r.totalPrice)')
            ->leftJoin('r.property', 'p')
            ->andWhere('p.host = :host')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('host', $host)
            ->setParameter('statuses', ['confirmed', 'completed'])
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (float) $result : 0.0;
    }

    public function countPendingReservationsByHost(User $host): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->leftJoin('r.property', 'p')
            ->andWhere('p.host = :host')
            ->andWhere('r.status = :status')
            ->setParameter('host', $host)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Reservation>
     */
    public function findExpiredPending(\DateTimeImmutable $threshold): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.createdAt < :threshold')
            ->setParameter('status', 'pending')
            ->setParameter('threshold', $threshold)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Reservation>
     */
    public function findConfirmedStartingOn(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.checkinDate = :date')
            ->setParameter('status', 'confirmed')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }
}

