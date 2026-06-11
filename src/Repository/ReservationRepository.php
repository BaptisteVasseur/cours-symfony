<?php

namespace App\Repository;

use App\Entity\Logement;
use App\Entity\Reservation;
use App\Entity\User;
use App\Enum\ReservationStatut;
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
    public function trouverPourVoyageur(User $voyageur): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.logement', 'l')
            ->addSelect('l')
            ->leftJoin('l.adresse', 'a')
            ->addSelect('a')
            ->leftJoin('l.photos', 'p')
            ->addSelect('p')
            ->andWhere('r.voyageur = :voyageur')
            ->setParameter('voyageur', $voyageur)
            ->orderBy('r.dateDemande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Reservation>
     */
    public function trouverPourHote(User $hote): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.logement', 'l')
            ->addSelect('l')
            ->leftJoin('r.voyageur', 'v')
            ->addSelect('v')
            ->andWhere('r.hote = :hote')
            ->setParameter('hote', $hote)
            ->orderBy('r.dateDemande', 'DESC')
            ->getQuery()
            ->getResult();
    }
    public function existeChevauchementConfirme(
        Logement $logement,
        \DateTimeInterface $dateArrivee,
        \DateTimeInterface $dateDepart,
        ?Reservation $reservationIgnoree = null,
    ): bool {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.logement = :logement')
            ->andWhere('r.statut = :statut')
            ->andWhere('r.dateArrivee < :dateDepart')
            ->andWhere('r.dateDepart > :dateArrivee')
            ->setParameter('logement', $logement)
            ->setParameter('statut', ReservationStatut::CONFIRMEE)
            ->setParameter('dateArrivee', \DateTimeImmutable::createFromInterface($dateArrivee)->setTime(0, 0))
            ->setParameter('dateDepart', \DateTimeImmutable::createFromInterface($dateDepart)->setTime(0, 0));

        if ($reservationIgnoree?->id !== null) {
            $qb
                ->andWhere('r.id != :reservationIgnoree')
                ->setParameter('reservationIgnoree', $reservationIgnoree->id);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

}
