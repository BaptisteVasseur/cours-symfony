<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Transitions de statut d'une réservation (B.2 modération, B.3 annulation).
 * Chaque transition est tracée dans ReservationStatusHistory.
 */
final class ReservationWorkflow
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReservationMailer $mailer,
    ) {
    }

    /**
     * Acceptation d'une demande par l'hôte : Pending -> Confirmed.
     */
    public function accept(Reservation $reservation, User $actor): void
    {
        $this->transition($reservation, 'confirmed', $actor);
        $this->entityManager->flush();

        // Partie D : réservation validée -> voyageur + hôte (asynchrone via Messenger).
        $this->mailer->sendConfirmation($reservation);
    }

    /**
     * Refus (B.2) ou annulation (B.3) : -> Cancelled avec motif obligatoire.
     * Le passage en Cancelled libère immédiatement les dates (calendrier et disponibilité ignorent ce statut).
     */
    public function cancel(Reservation $reservation, User $actor, string $reason): void
    {
        $reservation->setCancellationReason($reason);
        $this->transition($reservation, 'cancelled', $actor);
        $this->entityManager->flush();

        // Partie D : refus / annulation -> parties concernées avec le motif (asynchrone via Messenger).
        $this->mailer->sendCancellation($reservation);
    }

    private function transition(Reservation $reservation, string $newStatus, User $actor): void
    {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($reservation->getStatus());
        $history->setNewStatus($newStatus);
        $history->setChangedBy($actor);
        $this->entityManager->persist($history);

        $reservation->setStatus($newStatus);
    }
}
