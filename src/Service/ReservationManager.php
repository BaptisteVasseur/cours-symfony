<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Message\ReservationConfirmedMessage;
use App\Message\ReservationRejectedMessage;
use App\Message\ReservationCancelledMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Service to manage reservation state machine and transitions.
 */
class ReservationManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * Confirm a reservation (change status from pending to confirmed).
     */
    public function confirm(Reservation $reservation, User $changedBy): void
    {
        if ($reservation->getStatus() !== 'pending') {
            throw new \LogicException(sprintf(
                'Cannot confirm a reservation with status "%s". Only "pending" can be confirmed.',
                $reservation->getStatus()
            ));
        }

        $reservation->setStatus('confirmed');
        $this->recordStatusChange($reservation, 'pending', 'confirmed', $changedBy);
        $this->entityManager->flush();

        // Dispatch async notification
        $this->messageBus->dispatch(new ReservationConfirmedMessage($reservation->getId()));
    }

    /**
     * Reject a reservation (change status to cancelled with reason).
     */
    public function reject(Reservation $reservation, User $changedBy, string $reason): void
    {
        if ($reservation->getStatus() !== 'pending') {
            throw new \LogicException(sprintf(
                'Cannot reject a reservation with status "%s". Only "pending" can be rejected.',
                $reservation->getStatus()
            ));
        }

        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);
        $this->recordStatusChange($reservation, 'pending', 'cancelled', $changedBy, $reason);
        $this->entityManager->flush();

        // Dispatch async notification
        $this->messageBus->dispatch(new ReservationRejectedMessage($reservation->getId(), $reason));
    }

    /**
     * Cancel a reservation.
     */
    public function cancel(Reservation $reservation, User $changedBy, string $reason): void
    {
        if ($reservation->getStatus() === 'cancelled') {
            throw new \LogicException('Cannot cancel an already cancelled reservation.');
        }

        if ($reservation->getStatus() === 'completed') {
            throw new \LogicException('Cannot cancel a completed reservation.');
        }

        $previousStatus = $reservation->getStatus();
        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);
        $this->recordStatusChange($reservation, $previousStatus, 'cancelled', $changedBy, $reason);
        $this->entityManager->flush();

        // Dispatch async notification
        $this->messageBus->dispatch(new ReservationCancelledMessage($reservation->getId(), $reason));
    }

    /**
     * Mark a reservation as completed.
     */
    public function complete(Reservation $reservation, User $changedBy): void
    {
        if ($reservation->getStatus() !== 'confirmed') {
            throw new \LogicException(sprintf(
                'Cannot complete a reservation with status "%s". Only "confirmed" can be completed.',
                $reservation->getStatus()
            ));
        }

        // Check if checkout date has passed
        $today = new \DateTimeImmutable();
        if ($reservation->getCheckoutDate() > $today) {
            throw new \LogicException('Cannot complete a reservation before checkout date.');
        }

        $reservation->setStatus('completed');
        $this->recordStatusChange($reservation, 'confirmed', 'completed', $changedBy);
        $this->entityManager->flush();
    }

    /**
     * Record a status change in history.
     */
    private function recordStatusChange(
        Reservation $reservation,
        string $previousStatus,
        string $newStatus,
        User $changedBy,
        ?string $notes = null
    ): void {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($previousStatus);
        $history->setNewStatus($newStatus);
        $history->setChangedBy($changedBy);

        $this->entityManager->persist($history);
        $reservation->getStatusHistory()?->add($history);
    }
}
