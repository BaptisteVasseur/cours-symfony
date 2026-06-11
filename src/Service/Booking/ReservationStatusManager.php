<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use Doctrine\DBAL\Exception\ConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ReservationStatusManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AvailabilityChecker $availabilityChecker,
    ) {
    }

    public function accept(Reservation $reservation, User $host): void
    {
        if ($reservation->getStatus() !== 'pending') {
            throw new \DomainException('Cette demande n’est plus en attente.');
        }

        $property = $reservation->getProperty();
        $checkin = $reservation->getCheckinDate();
        $checkout = $reservation->getCheckoutDate();
        $guests = $reservation->getGuestsCount();

        if ($property === null || $checkin === null || $checkout === null || $guests === null) {
            throw new \DomainException('Cette demande de réservation est incomplète.');
        }

        try {
            $this->entityManager->wrapInTransaction(function () use ($reservation, $host, $property, $checkin, $checkout, $guests): void {
                $this->availabilityChecker->assertBookable($property, $checkin, $checkout, $guests);
                $oldStatus = $reservation->getStatus();

                $reservation->setStatus('confirmed');
                $this->addHistory($reservation, $oldStatus, 'confirmed', $host);

                $this->entityManager->flush();
            });
        } catch (ConstraintViolationException $exception) {
            if ($exception->getSQLState() !== '23P01') {
                throw $exception;
            }

            throw new \DomainException('Ces dates viennent d’être confirmées pour une autre réservation.', previous: $exception);
        }
    }

    public function reject(Reservation $reservation, User $host, string $reason): void
    {
        if ($reservation->getStatus() !== 'pending') {
            throw new \DomainException('Cette demande n’est plus en attente.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \DomainException('Le motif de refus est obligatoire.');
        }

        $oldStatus = $reservation->getStatus();
        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);
        $this->addHistory($reservation, $oldStatus, 'cancelled', $host);

        $this->entityManager->flush();
    }

    private function addHistory(Reservation $reservation, ?string $oldStatus, string $newStatus, User $changedBy): void
    {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus);
        $history->setNewStatus($newStatus);
        $history->setChangedBy($changedBy);

        $this->entityManager->persist($history);
    }
}
