<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Message\ReservationCancelledMessage;
use App\Message\ReservationConfirmedMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class ReservationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function confirm(Reservation $reservation, User $actor): void
    {
        $this->transition($reservation, 'confirmed', $actor);
        $this->entityManager->flush();
        $this->bus->dispatch(new ReservationConfirmedMessage((string) $reservation->getId()));
    }

    public function refuse(Reservation $reservation, User $actor, string $reason): void
    {
        $reservation->setCancellationReason($reason);
        $this->transition($reservation, 'cancelled', $actor);
        $this->entityManager->flush();
        $this->bus->dispatch(new ReservationCancelledMessage((string) $reservation->getId()));
    }

    public function cancel(Reservation $reservation, User $actor, string $reason): void
    {
        $reservation->setCancellationReason($reason);
        $this->transition($reservation, 'cancelled', $actor);
        $this->entityManager->flush();
        $this->bus->dispatch(new ReservationCancelledMessage((string) $reservation->getId()));
    }

    public function addStatusHistory(Reservation $reservation, ?string $oldStatus, string $newStatus, User $actor): void
    {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus);
        $history->setNewStatus($newStatus);
        $history->setChangedBy($actor);
        $this->entityManager->persist($history);
    }

    private function transition(Reservation $reservation, string $newStatus, User $actor): void
    {
        $oldStatus = $reservation->getStatus();
        $reservation->setStatus($newStatus);
        $this->addStatusHistory($reservation, $oldStatus, $newStatus, $actor);
    }
}
