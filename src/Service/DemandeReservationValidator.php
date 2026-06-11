<?php

namespace App\Service;

use App\Entity\Disponibilite;
use App\Entity\Logement;
use App\Entity\User;
use App\Enum\DisponibiliteStatut;
use App\Enum\LogementStatut;
use App\Enum\UserStatut;

class DemandeReservationValidator
{
    /**
     * @return list<string>
     */
    public function getMotifsInvalides(
        User $voyageur,
        Logement $logement,
        \DateTimeInterface $dateArrivee,
        \DateTimeInterface $dateDepart,
        int $nombreVoyageurs,
    ): array {
        $motifs = [];

        if ($voyageur->statut === UserStatut::SUSPENDU) {
            $motifs[] = 'Un utilisateur suspendu ne peut pas reserver.';
        }

        if (!$this->estMajeur($voyageur)) {
            $motifs[] = 'Le voyageur doit avoir au moins 18 ans pour reserver.';
        }

        if ($this->estLeMemeUtilisateur($voyageur, $logement->hote)) {
            $motifs[] = 'Un voyageur ne peut pas reserver son propre logement.';
        }

        if ($logement->statut === LogementStatut::SUSPENDU) {
            $motifs[] = 'Un logement suspendu ne peut pas recevoir de demande.';
        }

        if ($logement->statut === LogementStatut::ARCHIVE) {
            $motifs[] = 'Un logement archive ne peut pas recevoir de demande.';
        }

        if ($logement->statut !== LogementStatut::PUBLIE) {
            $motifs[] = 'Le logement doit etre publie pour recevoir une demande.';
        }

        if ($nombreVoyageurs < 1) {
            $motifs[] = 'Le nombre de voyageurs doit etre positif.';
        }

        if ($nombreVoyageurs > $logement->capaciteVoyageurs) {
            $motifs[] = 'Le nombre de voyageurs depasse la capacite du logement.';
        }

        if ($this->normaliserDate($dateArrivee) >= $this->normaliserDate($dateDepart)) {
            $motifs[] = 'La date de depart doit etre apres la date d arrivee.';
        } elseif (!$this->datesDisponibles($logement, $dateArrivee, $dateDepart)) {
            $motifs[] = 'Le logement n est pas disponible sur toute la periode demandee.';
        }

        return $motifs;
    }

    public function estValide(
        User $voyageur,
        Logement $logement,
        \DateTimeInterface $dateArrivee,
        \DateTimeInterface $dateDepart,
        int $nombreVoyageurs,
    ): bool {
        return $this->getMotifsInvalides($voyageur, $logement, $dateArrivee, $dateDepart, $nombreVoyageurs) === [];
    }

    private function estMajeur(User $user): bool
    {
        if ($user->dateNaissance === null) {
            return false;
        }

        return $user->dateNaissance->diff(new \DateTimeImmutable())->y >= 18;
    }

    private function estLeMemeUtilisateur(User $a, User $b): bool
    {
        if ($a->id !== null && $b->id !== null) {
            return $a->id === $b->id;
        }

        return $a === $b;
    }

    private function datesDisponibles(
        Logement $logement,
        \DateTimeInterface $dateArrivee,
        \DateTimeInterface $dateDepart,
    ): bool {
        $disponibilites = [];

        foreach ($logement->disponibilites as $disponibilite) {
            \assert($disponibilite instanceof Disponibilite);

            if ($disponibilite->statut !== DisponibiliteStatut::DISPONIBLE) {
                continue;
            }

            $disponibilites[$this->normaliserDate($disponibilite->date)->format('Y-m-d')] = true;
        }

        $date = $this->normaliserDate($dateArrivee);
        $depart = $this->normaliserDate($dateDepart);

        while ($date < $depart) {
            if (!isset($disponibilites[$date->format('Y-m-d')])) {
                return false;
            }

            $date = $date->modify('+1 day');
        }

        return true;
    }

    private function normaliserDate(\DateTimeInterface $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
    }
}
