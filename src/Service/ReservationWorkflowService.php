<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class ReservationWorkflowService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Applique une transition d'état, persiste l'entrée d'historique.
     * Le flush reste à la charge de l'appelant.
     */
    public function transition(
        Reservation $reservation,
        string $newStatus,
        User $actor,
        ?string $reason = null,
    ): void {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($reservation->getStatus());
        $history->setNewStatus($newStatus);
        $history->setChangedBy($actor);

        $reservation->setStatus($newStatus);

        if ($reason !== null) {
            $reservation->setCancellationReason($reason);
        }

        $reservation->addStatusHistory($history);
        $this->entityManager->persist($history);
    }
}
