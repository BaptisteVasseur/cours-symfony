<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ReservationLifecycleService
{
    private const ALLOWED_TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['cancelled', 'completed'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReservationRepository $reservationRepository,
    ) {
    }

    public function confirm(Reservation $reservation, User $changedBy): void
    {
        $property = $reservation->getProperty();
        $checkin = $reservation->getCheckinDate();
        $checkout = $reservation->getCheckoutDate();

        if ($property !== null && $checkin !== null && $checkout !== null
            && $this->reservationRepository->hasOverlap($property, $checkin, $checkout, $reservation)
        ) {
            throw new \LogicException('Ce logement est déjà réservé sur ces dates.');
        }

        $this->transition($reservation, 'confirmed', $changedBy);
    }

    public function cancel(Reservation $reservation, User $changedBy, ?string $reason = null): void
    {
        if ($reason !== null && $reason !== '') {
            $reservation->setCancellationReason($reason);
        }

        $this->transition($reservation, 'cancelled', $changedBy);
    }

    public function complete(Reservation $reservation, User $changedBy): void
    {
        $this->transition($reservation, 'completed', $changedBy);
    }

    private function transition(Reservation $reservation, string $newStatus, User $changedBy): void
    {
        $oldStatus = $reservation->getStatus();

        if ($oldStatus === null || !isset(self::ALLOWED_TRANSITIONS[$oldStatus])) {
            throw new \LogicException(sprintf('La réservation ne peut pas changer de statut depuis "%s".', $oldStatus ?? 'null'));
        }

        if (!in_array($newStatus, self::ALLOWED_TRANSITIONS[$oldStatus], true)) {
            throw new \LogicException(sprintf('Transition de "%s" vers "%s" non autorisée.', $oldStatus, $newStatus));
        }

        $reservation->setStatus($newStatus);

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus);
        $history->setNewStatus($newStatus);
        $history->setChangedBy($changedBy);

        $this->entityManager->persist($history);
        $this->entityManager->flush();
    }
}
