<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Exception\ReservationActionException;
use Doctrine\ORM\EntityManagerInterface;

final class ReservationStatusManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PropertyAvailabilityService $propertyAvailabilityService,
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

        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus('pending');
        $history->setNewStatus('cancelled');
        $history->setChangedBy($host);

        $this->entityManager->persist($history);
        $this->entityManager->flush();
    }
}
