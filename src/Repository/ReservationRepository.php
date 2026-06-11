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
}
