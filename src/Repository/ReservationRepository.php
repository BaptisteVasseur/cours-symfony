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

    public function existsConfirmedOverlap(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        ?Reservation $exclude = null,
    ): bool {
        $qb = $this->createQueryBuilder('r')
            ->select('1')
            ->andWhere('r.property = :property')
            ->andWhere('r.status = :status')
            ->andWhere('r.checkinDate < :checkout')
            ->andWhere('r.checkoutDate > :checkin')
            ->setParameter('property', $property)
            ->setParameter('status', 'confirmed')
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout)
            ->setMaxResults(1);

        if ($exclude !== null) {
            $qb->andWhere('r != :exclude')
                ->setParameter('exclude', $exclude);
        }

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }

    /**
     * @return list<Reservation>
     */
    /**
     * @return list<Reservation>
     */
    public function findConfirmedByProperty(Property $property): array
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

    /**
     * @return list<Reservation>
     */
    public function findPendingByHost(User $host): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('p', 'm', 'a', 'g', 'gp')
            ->leftJoin('r.property', 'p')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->leftJoin('r.guest', 'g')
            ->leftJoin('g.profile', 'gp')
            ->andWhere('p.host = :host')
            ->andWhere('r.status = :status')
            ->setParameter('host', $host)
            ->setParameter('status', 'pending')
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Reservation>
     */
    public function findExpiredPending(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.expiresAt IS NOT NULL')
            ->andWhere('r.expiresAt < :now')
            ->setParameter('status', 'pending')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Reservation>
     */
    public function findConfirmedPastCheckout(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.checkoutDate <= :today')
            ->setParameter('status', 'confirmed')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()
            ->getResult();
    }

    public function findConfirmedOverlappingMonth(Property $property, int $year, int $month): array
    {
        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $end = $start->modify('first day of next month');

        return $this->createQueryBuilder('r')
            ->addSelect('g', 'gp')
            ->leftJoin('r.guest', 'g')
            ->leftJoin('g.profile', 'gp')
            ->andWhere('r.property = :property')
            ->andWhere('r.status = :status')
            ->andWhere('r.checkinDate < :end')
            ->andWhere('r.checkoutDate > :start')
            ->setParameter('property', $property)
            ->setParameter('status', 'confirmed')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('r.checkinDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
