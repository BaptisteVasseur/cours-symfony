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

    public function sumRevenueForHost(User $host): float
    {
        $result = $this->createQueryBuilder('reservation')
            ->select('SUM(reservation.totalPrice)')
            ->innerJoin('reservation.property', 'property')
            ->andWhere('property.host = :host')
            ->andWhere('reservation.status IN (:statuses)')
            ->setParameter('host', $host)
            ->setParameter('statuses', ['confirmed', 'completed'])
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (float) $result : 0.0;
    }

    public function countForHostByStatus(User $host, ?string $status = null): int
    {
        $qb = $this->createQueryBuilder('reservation')
            ->select('COUNT(reservation.id)')
            ->innerJoin('reservation.property', 'property')
            ->andWhere('property.host = :host')
            ->setParameter('host', $host);

        if ($status !== null) {
            $qb->andWhere('reservation.status = :status')
                ->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function hasConfirmedOverlap(
        string $propertyId,
        \DateTimeImmutable $checkinDate,
        \DateTimeImmutable $checkoutDate,
        ?Reservation $ignoredReservation = null,
    ): bool {
        $qb = $this->createQueryBuilder('reservation')
            ->select('COUNT(reservation.id)')
            ->andWhere('IDENTITY(reservation.property) = :propertyId')
            ->andWhere('reservation.status = :status')
            ->andWhere('reservation.checkinDate < :checkoutDate')
            ->andWhere('reservation.checkoutDate > :checkinDate')
            ->setParameter('propertyId', $propertyId)
            ->setParameter('status', 'confirmed')
            ->setParameter('checkinDate', $checkinDate)
            ->setParameter('checkoutDate', $checkoutDate);

        if ($ignoredReservation !== null) {
            $qb->andWhere('reservation != :ignoredReservation')
                ->setParameter('ignoredReservation', $ignoredReservation);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * @return list<Reservation>
     */
    public function findForGuest(User $guest): array
    {
        return $this->createQueryBuilder('reservation')
            ->addSelect('property', 'media', 'address')
            ->leftJoin('reservation.property', 'property')
            ->leftJoin('property.media', 'media')
            ->leftJoin('property.address', 'address')
            ->andWhere('reservation.guest = :guest')
            ->setParameter('guest', $guest)
            ->orderBy('reservation.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Reservation>
     */
    public function findPendingForHost(User $host): array
    {
        return $this->createQueryBuilder('reservation')
            ->addSelect('property', 'guest', 'profile')
            ->innerJoin('reservation.property', 'property')
            ->leftJoin('reservation.guest', 'guest')
            ->leftJoin('guest.profile', 'profile')
            ->andWhere('property.host = :host')
            ->andWhere('reservation.status = :status')
            ->setParameter('host', $host)
            ->setParameter('status', 'pending')
            ->orderBy('reservation.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Reservation>
     */
    public function findConfirmedForProperty(string $propertyId): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('guest', 'profile')
            ->leftJoin('r.guest', 'guest')
            ->leftJoin('guest.profile', 'profile')
            ->andWhere('IDENTITY(r.property) = :propertyId')
            ->andWhere('r.status = :status')
            ->setParameter('propertyId', $propertyId)
            ->setParameter('status', 'confirmed')
            ->orderBy('r.checkinDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
