<?php

namespace App\Service;

use App\Entity\Disponibilite;
use App\Entity\Logement;
use App\Entity\Reservation;
use App\Enum\DisponibiliteStatut;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

class DisponibiliteService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReservationRepository $reservations,
    ) {
    }

    public function normaliserDate(\DateTimeInterface $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
    }

    public function calculerNombreNuits(\DateTimeInterface $dateArrivee, \DateTimeInterface $dateDepart): int
    {
        $arrivee = $this->normaliserDate($dateArrivee);
        $depart = $this->normaliserDate($dateDepart);

        if ($arrivee >= $depart) {
            return 0;
        }

        return (int) $arrivee->diff($depart)->days;
    }

    public function plageValide(\DateTimeInterface $dateArrivee, \DateTimeInterface $dateDepart): bool
    {
        return $this->calculerNombreNuits($dateArrivee, $dateDepart) > 0;
    }

    public function estDisponible(
        Logement $logement,
        \DateTimeInterface $dateArrivee,
        \DateTimeInterface $dateDepart,
        ?Reservation $reservationIgnoree = null,
    ): bool {
        $nombreNuits = $this->calculerNombreNuits($dateArrivee, $dateDepart);
        if ($nombreNuits === 0) {
            return false;
        }

        if ($this->reservations->existeChevauchementConfirme($logement, $dateArrivee, $dateDepart, $reservationIgnoree)) {
            return false;
        }

        return $this->compterJoursDisponibles($logement, $dateArrivee, $dateDepart) === $nombreNuits;
    }

    public function compterJoursDisponibles(
        Logement $logement,
        \DateTimeInterface $dateArrivee,
        \DateTimeInterface $dateDepart,
    ): int {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(Disponibilite::class, 'd')
            ->andWhere('d.logement = :logement')
            ->andWhere('d.date >= :dateArrivee')
            ->andWhere('d.date < :dateDepart')
            ->andWhere('d.statut = :statut')
            ->setParameter('logement', $logement)
            ->setParameter('dateArrivee', $this->normaliserDate($dateArrivee))
            ->setParameter('dateDepart', $this->normaliserDate($dateDepart))
            ->setParameter('statut', DisponibiliteStatut::DISPONIBLE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function reserverPeriode(Reservation $reservation): void
    {
        $this->changerStatutPeriode(
            $reservation->logement,
            $reservation->dateArrivee,
            $reservation->dateDepart,
            DisponibiliteStatut::RESERVEE,
        );
    }

    public function libererPeriodeReservee(Reservation $reservation): void
    {
        $disponibilites = $this->trouverDisponibilitesPeriode(
            $reservation->logement,
            $reservation->dateArrivee,
            $reservation->dateDepart,
        );

        foreach ($disponibilites as $disponibilite) {
            if ($disponibilite->statut !== DisponibiliteStatut::RESERVEE) {
                continue;
            }

            $disponibilite->statut = DisponibiliteStatut::DISPONIBLE;
            $disponibilite->raisonBlocage = null;
            $disponibilite->dateMiseAJour = new \DateTimeImmutable();
        }
    }

    public function bloquerPeriode(
        Logement $logement,
        \DateTimeInterface $dateDebut,
        \DateTimeInterface $dateFin,
        string $raison,
    ): void {
        $this->changerStatutPeriode(
            $logement,
            $dateDebut,
            $dateFin,
            DisponibiliteStatut::BLOQUEE,
            trim($raison) !== '' ? trim($raison) : 'Blocage manuel',
        );
    }

    private function changerStatutPeriode(
        Logement $logement,
        \DateTimeInterface $dateDebut,
        \DateTimeInterface $dateFin,
        DisponibiliteStatut $statut,
        ?string $raisonBlocage = null,
    ): void {
        $date = $this->normaliserDate($dateDebut);
        $fin = $this->normaliserDate($dateFin);
        $disponibilites = [];

        foreach ($this->trouverDisponibilitesPeriode($logement, $date, $fin) as $disponibilite) {
            $disponibilites[$disponibilite->date->format('Y-m-d')] = $disponibilite;
        }

        while ($date < $fin) {
            $cle = $date->format('Y-m-d');
            $disponibilite = $disponibilites[$cle] ?? null;

            if (!$disponibilite instanceof Disponibilite) {
                $disponibilite = new Disponibilite();
                $disponibilite->logement = $logement;
                $disponibilite->date = $date;
                $logement->disponibilites->add($disponibilite);
                $this->entityManager->persist($disponibilite);
            }

            $disponibilite->statut = $statut;
            $disponibilite->raisonBlocage = $statut === DisponibiliteStatut::BLOQUEE ? $raisonBlocage : null;
            $disponibilite->dateMiseAJour = new \DateTimeImmutable();

            $date = $date->modify('+1 day');
        }
    }

    /**
     * @return list<Disponibilite>
     */
    private function trouverDisponibilitesPeriode(
        Logement $logement,
        \DateTimeInterface $dateDebut,
        \DateTimeInterface $dateFin,
    ): array {
        return $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(Disponibilite::class, 'd')
            ->andWhere('d.logement = :logement')
            ->andWhere('d.date >= :dateDebut')
            ->andWhere('d.date < :dateFin')
            ->setParameter('logement', $logement)
            ->setParameter('dateDebut', $this->normaliserDate($dateDebut))
            ->setParameter('dateFin', $this->normaliserDate($dateFin))
            ->orderBy('d.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
