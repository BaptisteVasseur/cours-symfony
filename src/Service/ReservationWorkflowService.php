<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Enum\ReservationNotificationType;
use App\Exception\ReservationWorkflowException;
use App\Message\SendReservationNotification;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class ReservationWorkflowService
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['cancelled', 'completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReservationRepository $reservationRepository,
        private readonly AvailabilityService $availabilityService,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function confirm(Reservation $reservation, User $actor): void
    {
        $this->transition($reservation, 'confirmed', $actor, null, true);

        $this->dispatchNotification($reservation, ReservationNotificationType::ConfirmedToGuest);
        $this->dispatchNotification($reservation, ReservationNotificationType::ConfirmedToHost);
    }

    public function reject(Reservation $reservation, User $actor, string $reason): void
    {
        $this->transition($reservation, 'cancelled', $actor, $reason, false);

        $this->dispatchNotification($reservation, ReservationNotificationType::RejectedToGuest);
    }

    public function cancel(Reservation $reservation, User $actor, string $reason): void
    {
        $currentStatus = $reservation->getStatus();
        if ($currentStatus === null || !\in_array('cancelled', self::TRANSITIONS[$currentStatus] ?? [], true)) {
            throw new ReservationWorkflowException('Cette réservation ne peut pas être annulée.');
        }

        $this->transition($reservation, 'cancelled', $actor, $reason, false);

        $this->dispatchNotification($reservation, ReservationNotificationType::CancelledToGuest);
        $this->dispatchNotification($reservation, ReservationNotificationType::CancelledToHost);
    }

    public function expire(Reservation $reservation): void
    {
        $systemUser = $reservation->getProperty()?->getHost();
        if ($systemUser === null) {
            throw new ReservationWorkflowException('Impossible d\'expirer cette réservation.');
        }

        $this->transition(
            $reservation,
            'cancelled',
            $systemUser,
            'Demande expirée : aucune réponse de l\'hôte dans le délai imparti.',
            false,
        );

        $this->dispatchNotification($reservation, ReservationNotificationType::RejectedToGuest);
    }

    public function complete(Reservation $reservation, User $actor): void
    {
        $this->transition($reservation, 'completed', $actor, null, false);
    }

    private function transition(
        Reservation $reservation,
        string $newStatus,
        User $actor,
        ?string $cancellationReason,
        bool $checkAvailability,
    ): void {
        $currentStatus = $reservation->getStatus();
        if ($currentStatus === null) {
            throw new ReservationWorkflowException('Statut de réservation invalide.');
        }

        if (!\in_array($newStatus, self::TRANSITIONS[$currentStatus] ?? [], true)) {
            throw new ReservationWorkflowException(sprintf(
                'Transition impossible de "%s" vers "%s".',
                $currentStatus,
                $newStatus,
            ));
        }

        if ($checkAvailability && $newStatus === 'confirmed') {
            $property = $reservation->getProperty();
            $checkin = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();
            $guests = $reservation->getGuestsCount();

            if ($property === null || $checkin === null || $checkout === null || $guests === null) {
                throw new ReservationWorkflowException('Données de réservation incomplètes.');
            }

            $reason = $this->availabilityService->getUnavailabilityReason($property, $checkin, $checkout, $guests);
            if ($reason !== null) {
                throw new ReservationWorkflowException($reason);
            }

            if ($this->reservationRepository->existsConfirmedOverlap($property, $checkin, $checkout, $reservation)) {
                throw new ReservationWorkflowException('Les dates ne sont plus disponibles.');
            }
        }

        $reservation->setStatus($newStatus);

        if ($newStatus === 'cancelled') {
            $reservation->setCancellationReason($cancellationReason);
            $reservation->setExpiresAt(null);
        }

        if ($newStatus === 'confirmed') {
            $reservation->setExpiresAt(null);
        }

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($currentStatus);
        $history->setNewStatus($newStatus);
        $history->setChangedBy($actor);
        $this->entityManager->persist($history);

        $this->entityManager->flush();
    }

    public function recordInitialStatus(Reservation $reservation, User $actor): void
    {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus(null);
        $history->setNewStatus((string) $reservation->getStatus());
        $history->setChangedBy($actor);
        $this->entityManager->persist($history);
    }

    private function dispatchNotification(Reservation $reservation, ReservationNotificationType $type): void
    {
        $id = $reservation->getId();
        if ($id === null) {
            return;
        }

        $this->messageBus->dispatch(new SendReservationNotification((string) $id, $type));
    }
}
