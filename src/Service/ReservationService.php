<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Exception\UnavailableDatesException;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class ReservationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AvailabilityService $availabilityService,
        private readonly ReservationRepository $reservationRepository,
        private readonly MessageBusInterface $bus,
    ) {}

    /**
     * Creates a reservation after verifying availability.
     * Sets status to 'confirmed' (instantBooking) or 'pending'.
     * Appends an initial ReservationStatusHistory entry.
     *
     * @throws UnavailableDatesException
     */
    public function create(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guestsCount,
    ): Reservation {
        if (!$this->availabilityService->isAvailable($property, $checkin, $checkout, $guestsCount)) {
            throw new UnavailableDatesException($checkin, $checkout);
        }

        $nights = (int) $checkin->diff($checkout)->days;
        $nightlyRate = (float) $property->getPricePerNight();
        $subtotal = $nightlyRate * $nights;
        $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
        $serviceFee = round($subtotal * 0.12, 2);
        $totalPrice = round($subtotal + $cleaningFee + $serviceFee, 2);

        $status = $property->isInstantBooking() ? 'confirmed' : 'pending';

        $reservation = new Reservation();
        $reservation->setProperty($property);
        $reservation->setGuest($guest);
        $reservation->setCheckinDate($checkin);
        $reservation->setCheckoutDate($checkout);
        $reservation->setGuestsCount($guestsCount);
        $reservation->setStatus($status);
        $reservation->setTotalPrice((string) $totalPrice);
        $reservation->setCleaningFee($cleaningFee > 0 ? (string) $cleaningFee : null);
        $reservation->setServiceFee((string) $serviceFee);
        $reservation->setSecurityDeposit($property->getSecurityDeposit());
        $reservation->setCurrency('EUR');

        $this->em->persist($reservation);
        $this->appendStatusHistory($reservation, null, $status, $guest);
        $this->em->flush();

        $this->dispatchCreated((string) $reservation->getId());

        return $reservation;
    }

    /**
     * Confirms a pending reservation (host action).
     * Auto-cancels all other pending reservations that overlap on the same property.
     */
    public function confirm(Reservation $reservation, User $changedBy): void
    {
        if ($reservation->getStatus() !== 'pending') {
            throw new \LogicException('Seule une réservation en attente peut être confirmée.');
        }

        $oldStatus = $reservation->getStatus();
        $reservation->setStatus('confirmed');
        $this->appendStatusHistory($reservation, $oldStatus, 'confirmed', $changedBy);

        // Auto-cancel other pending reservations overlapping same dates
        $conflicting = $this->reservationRepository->findPendingOverlapping(
            $reservation->getProperty(),
            $reservation->getCheckinDate(),
            $reservation->getCheckoutDate(),
            $reservation,
        );

        foreach ($conflicting as $conflict) {
            $conflict->setStatus('cancelled');
            $conflict->setCancellationReason('Réservation annulée automatiquement suite à la confirmation d\'une autre demande.');
            $this->appendStatusHistory($conflict, 'pending', 'cancelled', $changedBy);
            $this->dispatchCancelled((string) $conflict->getId(), (string) $changedBy->getId());
        }

        $this->em->flush();
        $this->dispatchConfirmed((string) $reservation->getId());
    }

    /**
     * Rejects a pending reservation (host action).
     */
    public function reject(Reservation $reservation, User $changedBy, string $reason): void
    {
        if ($reservation->getStatus() !== 'pending') {
            throw new \LogicException('Seule une réservation en attente peut être refusée.');
        }

        $oldStatus = $reservation->getStatus();
        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);
        $this->appendStatusHistory($reservation, $oldStatus, 'cancelled', $changedBy);
        $this->em->flush();
        $this->dispatchCancelled((string) $reservation->getId(), (string) $changedBy->getId());
    }

    /**
     * Cancels a reservation (guest or host action).
     */
    public function cancel(Reservation $reservation, User $changedBy, string $reason): void
    {
        if (in_array($reservation->getStatus(), ['cancelled', 'completed'], true)) {
            throw new \LogicException('Cette réservation ne peut plus être annulée.');
        }

        $oldStatus = $reservation->getStatus();
        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);
        $this->appendStatusHistory($reservation, $oldStatus, 'cancelled', $changedBy);
        $this->em->flush();
        $this->dispatchCancelled((string) $reservation->getId(), (string) $changedBy->getId());
    }

    /**
     * Cancels all pending reservations older than the given threshold.
     * Returns the number of expired reservations.
     */
    public function expirePending(\DateInterval $threshold): int
    {
        $threshold = (new \DateTimeImmutable())->sub($threshold);
        $pending = $this->reservationRepository->findPendingOlderThan($threshold);

        foreach ($pending as $reservation) {
            $reservation->setStatus('cancelled');
            $reservation->setCancellationReason('Demande expirée — aucune réponse de l\'hôte sous 24h.');
            // System user not available — skip status history for automated expiry
        }

        $this->em->flush();

        return count($pending);
    }

    private function appendStatusHistory(
        Reservation $reservation,
        ?string $oldStatus,
        string $newStatus,
        User $changedBy,
    ): void {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus);
        $history->setNewStatus($newStatus);
        $history->setChangedBy($changedBy);
        $this->em->persist($history);
    }

    private function dispatchCreated(string $reservationId): void
    {
        if (!class_exists(\App\Message\ReservationCreatedMessage::class)) {
            return;
        }
        $this->bus->dispatch(new \App\Message\ReservationCreatedMessage($reservationId));
    }

    private function dispatchConfirmed(string $reservationId): void
    {
        if (!class_exists(\App\Message\ReservationConfirmedMessage::class)) {
            return;
        }
        $this->bus->dispatch(new \App\Message\ReservationConfirmedMessage($reservationId));
    }

    private function dispatchCancelled(string $reservationId, string $cancelledByUserId): void
    {
        if (!class_exists(\App\Message\ReservationCancelledMessage::class)) {
            return;
        }
        $this->bus->dispatch(new \App\Message\ReservationCancelledMessage($reservationId, $cancelledByUserId));
    }
}
