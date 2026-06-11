<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class ReservationStatusService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function transition(
        Reservation $reservation,
        string $newStatus,
        User $actor,
        ?string $oldStatus = null,
    ): void {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus ?? $reservation->getStatus());
        $history->setNewStatus($newStatus);
        $history->setChangedBy($actor);
        $reservation->setStatus($newStatus);
        $this->em->persist($history);
    }
}
