<?php

namespace App\Repository;

use App\Entity\Logement;
use App\Enum\LogementStatut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Logement>
 */
class LogementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Logement::class);
    }

    /**
     * @return list<Logement>
     */
    public function rechercherPublies(array $criteres): array
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.adresse', 'a')
            ->addSelect('a')
            ->leftJoin('l.tarif', 't')
            ->addSelect('t')
            ->leftJoin('l.photos', 'p')
            ->addSelect('p')
            ->andWhere('l.statut = :statut')
            ->setParameter('statut', LogementStatut::PUBLIE)
            ->orderBy('l.noteMoyenne', 'DESC')
            ->addOrderBy('l.datePublication', 'DESC');

        $destination = strtolower(trim((string) ($criteres['destination'] ?? '')));
        if ($destination !== '') {
            $qb
                ->andWhere('LOWER(a.ville) LIKE :destination OR LOWER(a.pays) LIKE :destination')
                ->setParameter('destination', '%'.$destination.'%');
        }

        $voyageurs = (int) ($criteres['voyageurs'] ?? 0);
        if ($voyageurs > 0) {
            $qb
                ->andWhere('l.capaciteVoyageurs >= :voyageurs')
                ->setParameter('voyageurs', $voyageurs);
        }

        $prixMax = (float) str_replace(',', '.', (string) ($criteres['prix_max'] ?? '0'));
        if ($prixMax > 0) {
            $qb
                ->andWhere('t.prixNuit <= :prixMax')
                ->setParameter('prixMax', number_format($prixMax, 2, '.', ''));
        }

        $type = trim((string) ($criteres['type'] ?? ''));
        if ($type !== '') {
            $qb
                ->andWhere('l.typeLogement = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<Logement>
     */
    public function trouverPopulaires(int $limite = 3): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.adresse', 'a')
            ->addSelect('a')
            ->leftJoin('l.tarif', 't')
            ->addSelect('t')
            ->leftJoin('l.photos', 'p')
            ->addSelect('p')
            ->andWhere('l.statut = :statut')
            ->setParameter('statut', LogementStatut::PUBLIE)
            ->orderBy('l.noteMoyenne', 'DESC')
            ->addOrderBy('l.nombreAvis', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}
