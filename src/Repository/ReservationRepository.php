<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\Reservation;
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
     * Retourne les réservations actives qui chevauchent la plage [checkin, checkout[.
     * Règle : checkout existant = checkin nouveau → autorisé (non retourné).
     *
     * @return Reservation[]
     */
    public function findConflictingReservations(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): array {
        return $this->createQueryBuilder('r')
            ->where('r.property = :property')
            ->andWhere('r.status != :cancelled')
            ->andWhere('r.checkinDate < :checkout')
            ->andWhere('r.checkoutDate > :checkin')
            ->setParameter('property', $property->getId(), 'uuid')
            ->setParameter('cancelled', 'cancelled')
            ->setParameter('checkin', $checkin->format('Y-m-d'))
            ->setParameter('checkout', $checkout->format('Y-m-d'))
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Reservation[]
     */
    public function findByHost(\App\Entity\User $host): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.property', 'p')
            ->where('p.host = :host')
            ->setParameter('host', $host->getId(), 'uuid')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

