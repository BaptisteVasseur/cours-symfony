<?php

declare(strict_types=1);

namespace App\Repository;

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
    public function findAllForListing(
        ?string $search = null,
        ?string $status = null,
        string $sort = 'createdAt',
        string $dir = 'DESC',
    ): array {
        $allowedSorts = ['createdAt' => 'r.createdAt', 'checkinDate' => 'r.checkinDate', 'checkoutDate' => 'r.checkoutDate', 'totalPrice' => 'r.totalPrice', 'status' => 'r.status'];
        $orderCol = $allowedSorts[$sort] ?? 'r.createdAt';
        $orderDir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('r')
            ->addSelect('p', 'm', 'a', 'g', 'gp')
            ->leftJoin('r.property', 'p')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->leftJoin('r.guest', 'g')
            ->leftJoin('g.profile', 'gp')
            ->orderBy($orderCol, $orderDir);

        if ($status !== null && $status !== '') {
            $qb->andWhere('r.status = :status')
                ->setParameter('status', $status);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('p.title LIKE :search OR g.email LIKE :search OR gp.firstName LIKE :search OR gp.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
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

    /** @return list<Reservation> */
    public function findPendingExpired(): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('p', 'g')
            ->leftJoin('r.property', 'p')
            ->leftJoin('r.guest', 'g')
            ->andWhere('r.status = :status')
            ->andWhere('r.expiresAt < :now')
            ->setParameter('status', 'pending')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /** @return list<Reservation> */
    public function findConfirmedToComplete(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.checkoutDate < :today')
            ->setParameter('status', 'confirmed')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()
            ->getResult();
    }

    /** @return list<Reservation> */
    public function findByGuestOrderedByDate(\App\Entity\User $guest): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('p', 'm', 'a')
            ->leftJoin('r.property', 'p')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->andWhere('r.guest = :guest')
            ->setParameter('guest', $guest)
            ->orderBy('r.checkinDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Reservation> */
    public function findPendingByHost(\App\Entity\User $host): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('p', 'g', 'gp')
            ->leftJoin('r.property', 'p')
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

    /** @return list<Reservation> */
    public function findConfirmedByProperty(\App\Entity\Property $property): array
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

    /** @return list<Reservation> */
    public function findByPropertyAndPeriod(\App\Entity\Property $property, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.property = :property')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.checkinDate < :to')
            ->andWhere('r.checkoutDate > :from')
            ->setParameter('property', $property)
            ->setParameter('statuses', ['confirmed', 'pending'])
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Reservation> Pending qui chevauchent une plage, pour un logement */
    public function findPendingOverlapping(\App\Entity\Property $property, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('g')
            ->leftJoin('r.guest', 'g')
            ->andWhere('r.property = :property')
            ->andWhere('r.status = :status')
            ->andWhere('r.checkinDate < :to')
            ->andWhere('r.checkoutDate > :from')
            ->setParameter('property', $property)
            ->setParameter('status', 'pending')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Reservation> All reservations across all properties owned by a host */
    public function findAllByHost(\App\Entity\User $host): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('p', 'g', 'gp')
            ->leftJoin('r.property', 'p')
            ->leftJoin('r.guest', 'g')
            ->leftJoin('g.profile', 'gp')
            ->andWhere('p.host = :host')
            ->setParameter('host', $host)
            ->orderBy('r.checkinDate', 'DESC')
            ->getQuery()
            ->getResult();
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
}
