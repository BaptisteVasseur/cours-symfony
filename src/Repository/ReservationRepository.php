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
     * @param list<string> $statuses Statuts considérés comme bloquants (par défaut seul 'confirmed').
     */
    public function hasOverlap(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        array $statuses = ['confirmed'],
        ?Reservation $exclude = null,
    ): bool {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.property = :property')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.checkinDate < :checkout')
            ->andWhere('r.checkoutDate > :checkin')
            ->setParameter('property', $property)
            ->setParameter('statuses', $statuses)
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout);

        if ($exclude !== null) {
            $qb->andWhere('r != :exclude')->setParameter('exclude', $exclude);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * @return list<Reservation>
     */
    public function findPendingForHost(User $host): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('p', 'm', 'a', 'g', 'gp')
            ->leftJoin('r.property', 'p')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->leftJoin('r.guest', 'g')
            ->leftJoin('g.profile', 'gp')
            ->andWhere('p.host = :host')
            ->andWhere('r.status = :pending')
            ->setParameter('host', $host)
            ->setParameter('pending', 'pending')
            ->orderBy('r.createdAt', 'ASC')
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

    /**
     * @return list<Reservation>
     */
    public function findConfirmedForProperty(Property $property): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('g', 'gp')
            ->leftJoin('r.guest', 'g')
            ->leftJoin('g.profile', 'gp')
            ->andWhere('r.property = :property')
            ->andWhere('r.status = :confirmed')
            ->setParameter('property', $property)
            ->setParameter('confirmed', 'confirmed')
            ->orderBy('r.checkinDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Reservation>
     */
    public function findConfirmedOverlapping(Property $property, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.property = :property')
            ->andWhere('r.status = :confirmed')
            ->andWhere('r.checkinDate < :to')
            ->andWhere('r.checkoutDate > :from')
            ->setParameter('property', $property)
            ->setParameter('confirmed', 'confirmed')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }

    /**
     * Demandes en attente créées avant un seuil donné (expiration automatique, G.1).
     *
     * @return list<Reservation>
     */
    public function findPendingOlderThan(\DateTimeImmutable $threshold): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :pending')
            ->andWhere('r.createdAt < :threshold')
            ->setParameter('pending', 'pending')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }

    /**
     * Séjours confirmés dont l'arrivée est à une date donnée (rappel check-in, G.2).
     *
     * @return list<Reservation>
     */
    public function findConfirmedWithCheckinOn(\DateTimeImmutable $date): array
    {
        $dayStart = $date->setTime(0, 0);
        $dayEnd = $dayStart->modify('+1 day');

        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :confirmed')
            ->andWhere('r.checkinDate >= :dayStart')
            ->andWhere('r.checkinDate < :dayEnd')
            ->setParameter('confirmed', 'confirmed')
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd', $dayEnd)
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
}
