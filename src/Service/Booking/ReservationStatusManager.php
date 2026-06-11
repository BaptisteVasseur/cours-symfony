<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Exception\ReservationActionException;
use App\Service\ReservationNotificationDispatcher;
use Doctrine\ORM\EntityManagerInterface;

final class ReservationStatusManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PropertyAvailabilityService $propertyAvailabilityService,
        private ReservationNotificationDispatcher $reservationNotificationDispatcher,
    ) {
    }

    public function confirmPending(Reservation $reservation, User $host): void
    {
        if ($reservation->getStatus() !== 'pending') {
            throw new ReservationActionException('Seules les demandes en attente peuvent etre acceptees.');
        }

        $property = $reservation->getProperty();
        if ($property === null) {
            throw new ReservationActionException('Le logement associe a cette reservation est introuvable.');
        }

        $checkin = $reservation->getCheckinDate();
        $checkout = $reservation->getCheckoutDate();
        $guestsCount = $reservation->getGuestsCount();

        if ($checkin === null || $checkout === null || $guestsCount === null) {
            throw new ReservationActionException('Cette reservation est incomplete et ne peut pas etre acceptee.');
        }

        try {
            $this->propertyAvailabilityService->assertBookable($property, $checkin, $checkout, $guestsCount);
        } catch (\RuntimeException $e) {
            throw new ReservationActionException($e->getMessage(), 0, $e);
        }

        $reservation->setStatus('confirmed');
        $reservation->setCancellationReason(null);

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus('pending');
        $history->setNewStatus('confirmed');
        $history->setChangedBy($host);

        $this->entityManager->persist($history);
        $this->entityManager->flush();
        $this->reservationNotificationDispatcher->dispatchReservationConfirmed($reservation);
    }

    public function rejectPending(Reservation $reservation, User $host, string $reason): void
    {
        if ($reservation->getStatus() !== 'pending') {
            throw new ReservationActionException('Seules les demandes en attente peuvent etre refusees.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new ReservationActionException('Un motif est obligatoire pour refuser une demande.');
        }

        $this->transitionToCancelled($reservation, $host, $reason);
        $this->reservationNotificationDispatcher->dispatchReservationRejected($reservation);
    }

    public function cancel(Reservation $reservation, User $actor, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new ReservationActionException('Un motif est obligatoire pour annuler une reservation.');
        }

        if (!in_array($reservation->getStatus(), ['pending', 'confirmed'], true)) {
            throw new ReservationActionException('Seules les reservations en attente ou confirmees peuvent etre annulees.');
        }

        $this->transitionToCancelled($reservation, $actor, $reason);
        $this->reservationNotificationDispatcher->dispatchReservationCancelled(
            $reservation,
            $this->resolveInitiator($reservation, $actor),
        );
    }

    public function expirePending(Reservation $reservation, string $reason): bool
    {
        if ($reservation->getStatus() !== 'pending') {
            return false;
        }

        $createdAt = $reservation->getCreatedAt();
        if ($createdAt === null || $createdAt > new \DateTimeImmutable('-24 hours')) {
            return false;
        }

        $this->transitionToCancelled($reservation, null, trim($reason) !== '' ? trim($reason) : 'La demande a expire apres 24h sans reponse.');
        $this->reservationNotificationDispatcher->dispatchReservationCancelled(
            $reservation,
            \App\Message\ReservationNotificationMessage::INITIATOR_SYSTEM,
        );

        return true;
    }

    private function transitionToCancelled(Reservation $reservation, ?User $changedBy, string $reason): void
    {
        $oldStatus = $reservation->getStatus();
        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus);
        $history->setNewStatus('cancelled');
        $history->setChangedBy($changedBy);

        $this->entityManager->persist($history);
        $this->entityManager->flush();
    }

    private function resolveInitiator(Reservation $reservation, User $actor): string
    {
        if ($reservation->getGuest()?->getId() === $actor->getId()) {
            return \App\Message\ReservationNotificationMessage::INITIATOR_GUEST;
        }

        if ($reservation->getProperty()?->getHost()?->getId() === $actor->getId()) {
            return \App\Message\ReservationNotificationMessage::INITIATOR_HOST;
        }

        return \App\Message\ReservationNotificationMessage::INITIATOR_SYSTEM;
    }
}
