<?php

declare(strict_types=1);

namespace App\Repository;

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

    public function hasOverlap(
        \App\Entity\Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        ?Reservation $exclude = null,
    ): bool {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.property = :property')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.checkinDate < :checkout')
            ->andWhere('r.checkoutDate > :checkin')
            ->setParameter('property', $property)
            ->setParameter('statuses', ['pending', 'confirmed'])
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout);

        if ($exclude !== null) {
            $qb->andWhere('r != :exclude')
                ->setParameter('exclude', $exclude);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * @return list<Reservation>
     */
    public function findExpiredPending(\DateTimeImmutable $expireBefore): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.createdAt < :expireBefore')
            ->setParameter('status', 'pending')
            ->setParameter('expireBefore', $expireBefore)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Reservation>
     */
    public function findConfirmedPastCheckout(\DateTimeImmutable $before): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.checkoutDate < :before')
            ->setParameter('status', 'confirmed')
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Reservation>
     */
    public function findForICalExport(\App\Entity\Property $property): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('g', 'gp')
            ->leftJoin('r.guest', 'g')
            ->leftJoin('g.profile', 'gp')
            ->andWhere('r.property = :property')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('property', $property)
            ->setParameter('statuses', ['confirmed', 'completed'])
            ->orderBy('r.checkinDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Reservation>
     */
    public function findConfirmedForPropertyAndMonth(
        \App\Entity\Property $property,
        \DateTimeImmutable $monthStart,
        \DateTimeImmutable $monthEnd,
    ): array {
        return $this->createQueryBuilder('r')
            ->addSelect('g', 'gp')
            ->leftJoin('r.guest', 'g')
            ->leftJoin('g.profile', 'gp')
            ->andWhere('r.property = :property')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.checkinDate <= :monthEnd')
            ->andWhere('r.checkoutDate >= :monthStart')
            ->setParameter('property', $property)
            ->setParameter('statuses', ['confirmed', 'completed'])
            ->setParameter('monthStart', $monthStart)
            ->setParameter('monthEnd', $monthEnd)
            ->orderBy('r.checkinDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
