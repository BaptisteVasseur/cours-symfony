<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Message\ReservationConfirmationNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Centralise les transitions de statut d'une réservation.
 *
 * Garantit, pour chaque changement :
 *   - la traçabilité (ReservationStatusHistory : ancien/nouveau statut + auteur),
 *   - la persistance,
 *   - le déclenchement de la notification email asynchrone.
 *
 * Une annulation (cancel) libère immédiatement les dates : l'algorithme de
 * disponibilité ne considère que les réservations au statut "confirmed", donc
 * une réservation annulée cesse instantanément de bloquer le calendrier.
 */
final class ReservationStatusManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * Confirme une réservation en attente (pending -> confirmed).
     */
    public function confirm(Reservation $reservation, User $actor): void
    {
        $this->transition($reservation, 'confirmed', $actor);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new ReservationConfirmationNotification((string) $reservation->getId()));
    }

    /**
     * Annule une réservation (pending|confirmed -> cancelled).
     * Le motif est OBLIGATOIRE quelle que soit la partie à l'initiative.
     */
    public function cancel(Reservation $reservation, string $reason, User $actor): void
    {
        $reservation->setCancellationReason($reason);
        $this->transition($reservation, 'cancelled', $actor);
        $this->entityManager->flush();

        // Notifie les DEUX parties (voyageur + hôte) — voir le MessageHandler.
        $this->messageBus->dispatch(new ReservationConfirmationNotification((string) $reservation->getId()));
    }

    private function transition(Reservation $reservation, string $newStatus, User $actor): void
    {
        $history = (new ReservationStatusHistory())
            ->setReservation($reservation)
            ->setOldStatus($reservation->getStatus())
            ->setNewStatus($newStatus)
            ->setChangedBy($actor);

        $reservation->addStatusHistory($history);
        $reservation->setStatus($newStatus);
        $this->entityManager->persist($history);
    }
}
