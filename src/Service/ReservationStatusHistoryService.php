<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class ReservationStatusHistoryService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function record(Reservation $reservation, ?string $oldStatus, string $newStatus, User $changedBy): void
    {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus);
        $history->setNewStatus($newStatus);
        $history->setChangedBy($changedBy);

        $reservation->addStatusHistory($history);
        $this->entityManager->persist($history);
    }
}

