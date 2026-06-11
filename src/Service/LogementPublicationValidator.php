<?php

namespace App\Service;

use App\Entity\Logement;

class LogementPublicationValidator
{
    /**
     * @return list<string>
     */
    public function getMotifsInvalides(Logement $logement): array
    {
        $motifs = [];

        if (trim($logement->titre) === '') {
            $motifs[] = 'Le titre est obligatoire.';
        }

        if (trim($logement->description) === '') {
            $motifs[] = 'La description est obligatoire.';
        }

        if ($logement->adresse === null) {
            $motifs[] = 'Une adresse est obligatoire.';
        }

        if ($logement->photos->isEmpty()) {
            $motifs[] = 'Au moins une photo est obligatoire.';
        }

        if ($logement->tarif === null) {
            $motifs[] = 'Un tarif est obligatoire.';
        }

        if ($logement->disponibilites->isEmpty()) {
            $motifs[] = 'Au moins une disponibilite est obligatoire.';
        }

        if ($logement->reglementInterieur === null) {
            $motifs[] = 'Un reglement interieur est obligatoire.';
        }

        if ($logement->politiqueAnnulation === null) {
            $motifs[] = 'Une politique d annulation est obligatoire.';
        }

        return $motifs;
    }

    public function estPubliable(Logement $logement): bool
    {
        return $this->getMotifsInvalides($logement) === [];
    }
}
