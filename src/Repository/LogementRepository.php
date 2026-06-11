<?php

namespace App\Repository;

use App\Entity\Logement;
use App\Enum\DisponibiliteStatut;
use App\Enum\LogementStatut;
use App\Enum\ReservationStatut;
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

        $voyageurs = (int) ($criteres['guests'] ?? ($criteres['voyageurs'] ?? 0));
        if ($voyageurs > 0) {
            $qb
                ->andWhere('l.capaciteVoyageurs >= :voyageurs')
                ->setParameter('voyageurs', $voyageurs);
        }

        $dateArrivee = $this->creerDate((string) ($criteres['checkin'] ?? ''));
        $dateDepart = $this->creerDate((string) ($criteres['checkout'] ?? ''));
        if ($dateArrivee !== null && $dateDepart !== null && $dateArrivee < $dateDepart) {
            $nombreNuits = (int) $dateArrivee->diff($dateDepart)->days;

            $qb
                ->andWhere('l.id IN (
                    SELECT IDENTITY(d2.logement)
                    FROM App\Entity\Disponibilite d2
                    WHERE d2.date >= :checkin
                    AND d2.date < :checkout
                    AND d2.statut = :statutDisponible
                    GROUP BY d2.logement
                    HAVING COUNT(d2.id) = :nombreNuits
                )')
                ->andWhere('l.id NOT IN (
                    SELECT IDENTITY(r2.logement)
                    FROM App\Entity\Reservation r2
                    WHERE r2.statut = :statutConfirme
                    AND r2.dateArrivee < :checkout
                    AND r2.dateDepart > :checkin
                )')
                ->setParameter('checkin', $dateArrivee)
                ->setParameter('checkout', $dateDepart)
                ->setParameter('statutDisponible', DisponibiliteStatut::DISPONIBLE)
                ->setParameter('statutConfirme', ReservationStatut::CONFIRMEE)
                ->setParameter('nombreNuits', $nombreNuits);
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

    private function creerDate(string $valeur): ?\DateTimeImmutable
    {
        if ($valeur === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);

        return $date instanceof \DateTimeImmutable ? $date : null;
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
