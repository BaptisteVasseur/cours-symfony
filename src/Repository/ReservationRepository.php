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

    /**
     * Count confirmed reservations overlapping with given date range.
     * Optimized for single SQL query (performance critical).
     */
    public function countOverlappingReservations(
        Property $property,
        \DateTimeImmutable $checkinDate,
        \DateTimeImmutable $checkoutDate,
        ?Reservation $excludeReservation = null
    ): int {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.property = :property')
            ->andWhere('r.status = :status')
            ->andWhere('r.checkinDate < :checkoutDate')
            ->andWhere('r.checkoutDate > :checkinDate')
            ->setParameter('property', $property)
            ->setParameter('status', 'confirmed')
            ->setParameter('checkinDate', $checkinDate)
            ->setParameter('checkoutDate', $checkoutDate);

        if ($excludeReservation !== null) {
            $qb->andWhere('r.id != :excludeReservation')
                ->setParameter('excludeReservation', $excludeReservation);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Find confirmed reservations overlapping with given date range.
     *
     * @return list<Reservation>
     */
    public function findOverlappingReservations(
        Property $property,
        \DateTimeImmutable $checkinDate,
        \DateTimeImmutable $checkoutDate,
        ?Reservation $excludeReservation = null
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->addSelect('g', 'gp')
            ->leftJoin('r.guest', 'g')
            ->leftJoin('g.profile', 'gp')
            ->andWhere('r.property = :property')
            ->andWhere('r.status = :status')
            ->andWhere('r.checkinDate < :checkoutDate')
            ->andWhere('r.checkoutDate > :checkinDate')
            ->setParameter('property', $property)
            ->setParameter('status', 'confirmed')
            ->setParameter('checkinDate', $checkinDate)
            ->setParameter('checkoutDate', $checkoutDate)
            ->orderBy('r.checkinDate', 'ASC');

        if ($excludeReservation !== null) {
            $qb->andWhere('r.id != :excludeReservation')
                ->setParameter('excludeReservation', $excludeReservation);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find pending reservations for a property (for host moderation).
     *
     * @return list<Reservation>
     */
    public function findPendingByProperty(Property $property): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('g', 'gp', 'p', 'a')
            ->leftJoin('r.guest', 'g')
            ->leftJoin('g.profile', 'gp')
            ->leftJoin('r.property', 'p')
            ->leftJoin('p.address', 'a')
            ->andWhere('r.property = :property')
            ->andWhere('r.status = :status')
            ->setParameter('property', $property)
            ->setParameter('status', 'pending')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find reservations for host dashboard.
     *
     * @return list<Reservation>
     */
    public function findByHostForListing(User $host): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('p', 'm', 'g', 'gp')
            ->leftJoin('r.property', 'p')
            ->leftJoin('p.media', 'm')
            ->leftJoin('r.guest', 'g')
            ->leftJoin('g.profile', 'gp')
            ->andWhere('p.host = :host')
            ->setParameter('host', $host)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
