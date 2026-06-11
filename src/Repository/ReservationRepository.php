<?php

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
}
