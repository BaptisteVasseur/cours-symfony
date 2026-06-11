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
     * Historique des réservations, les plus récentes d'abord.
     * Si un voyageur est fourni, filtre sur ses réservations.
     *
     * @return Reservation[]
     */
    public function findHistory(?User $guest = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.property', 'p')->addSelect('p')
            ->leftJoin('p.address', 'a')->addSelect('a')
            ->orderBy('r.createdAt', 'DESC');

        if ($guest !== null) {
            $qb->andWhere('r.guest = :guest')->setParameter('guest', $guest);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Réservations confirmées qui chevauchent la plage [checkin, checkout[.
     * Le jour de départ est libre : deux séjours peuvent se toucher (départ = arrivée).
     *
     * @return Reservation[]
     */
    public function findOverlappingConfirmed(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        ?Reservation $exclude = null,
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.property = :property')
            ->andWhere('r.status = :status')
            ->andWhere('r.checkinDate < :checkout')
            ->andWhere('r.checkoutDate > :checkin')
            ->setParameter('property', $property)
            ->setParameter('status', Reservation::STATUS_CONFIRMED)
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout);

        if ($exclude !== null) {
            $qb->andWhere('r != :exclude')->setParameter('exclude', $exclude);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Demandes en attente d'un hôte, pour le tableau de modération.
     *
     * @return Reservation[]
     */
    public function findPendingForHost(User $host): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.property', 'p')->addSelect('p')
            ->leftJoin('p.address', 'a')->addSelect('a')
            ->leftJoin('r.guest', 'g')->addSelect('g')
            ->andWhere('p.host = :host')
            ->andWhere('r.status = :status')
            ->setParameter('host', $host)
            ->setParameter('status', Reservation::STATUS_PENDING)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Demandes en attente créées avant le seuil donné (expiration automatique).
     *
     * @return Reservation[]
     */
    public function findExpiredPending(\DateTimeImmutable $threshold): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.createdAt < :threshold')
            ->setParameter('status', Reservation::STATUS_PENDING)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }

    /**
     * Réservations confirmées de TOUS les logements d'un hôte qui chevauchent la plage.
     * Sert le calendrier global de l'hôte.
     *
     * @return Reservation[]
     */
    public function findConfirmedForHostInRange(User $host, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.property', 'p')->addSelect('p')
            ->andWhere('p.host = :host')
            ->andWhere('r.status = :status')
            ->andWhere('r.checkinDate < :to')
            ->andWhere('r.checkoutDate > :from')
            ->setParameter('host', $host)
            ->setParameter('status', Reservation::STATUS_CONFIRMED)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }

    /**
     * Réservations confirmées d'un logement à exporter au format iCal.
     *
     * @return Reservation[]
     */
    public function findConfirmedForCalendar(Property $property): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.guest', 'g')->addSelect('g')
            ->andWhere('r.property = :property')
            ->andWhere('r.status = :status')
            ->setParameter('property', $property)
            ->setParameter('status', Reservation::STATUS_CONFIRMED)
            ->orderBy('r.checkinDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
