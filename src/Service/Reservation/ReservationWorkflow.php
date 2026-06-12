<?php

declare(strict_types=1);

namespace App\Service\Reservation;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Enum\ReservationNotificationType;
use App\Message\ReservationNotification;
use App\Service\Availability\AvailabilityChecker;
use App\Service\Reservation\Exception\InvalidReservationTransitionException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Point de passage unique du cycle de vie d'une réservation : garde des transitions autorisées,
 * historisation (ReservationStatusHistory) et notification asynchrone. Réutilise A.2 pour la
 * revalidation atomique lors de la confirmation.
 */
final class ReservationWorkflow
{
    public const PENDING = 'pending';
    public const CONFIRMED = 'confirmed';
    public const CANCELLED = 'cancelled';
    public const COMPLETED = 'completed';

    /** @var array<string, list<string>> */
    private const ALLOWED_TRANSITIONS = [
        self::PENDING => [self::CONFIRMED, self::CANCELLED],
        self::CONFIRMED => [self::CANCELLED],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AvailabilityChecker $availabilityChecker,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * Historise la création (null → statut courant). À appeler dans la transaction de création.
     */
    public function recordCreation(Reservation $reservation, User $actor): void
    {
        $this->addHistory($reservation, null, (string) $reservation->getStatus(), $actor);
    }

    /**
     * Notifie la création (après commit) : confirmation immédiate ou demande en attente.
     */
    public function notifyCreated(Reservation $reservation): void
    {
        $this->dispatch(
            $reservation,
            $reservation->getStatus() === self::CONFIRMED
                ? ReservationNotificationType::CREATED_CONFIRMED
                : ReservationNotificationType::CREATED_PENDING,
        );
    }

    /**
     * Acceptation hôte : pending → confirmed, avec revalidation atomique des dates (A.2).
     *
     * @throws InvalidReservationTransitionException
     * @throws \App\Service\Availability\Exception\PropertyNotAvailableException si les dates ne sont plus libres
     */
    public function confirm(Reservation $reservation, User $host): void
    {
        $this->assertTransitionAllowed((string) $reservation->getStatus(), self::CONFIRMED);

        $this->availabilityChecker->assertAvailableWithLock(
            $reservation->getProperty(),
            $reservation->getCheckinDate(),
            $reservation->getCheckoutDate(),
            (int) $reservation->getGuestsCount(),
            function () use ($reservation, $host): void {
                $reservation->setStatus(self::CONFIRMED);
                $this->addHistory($reservation, self::PENDING, self::CONFIRMED, $host);
            },
        );

        $this->dispatch($reservation, ReservationNotificationType::ACCEPTED);
    }

    /**
     * Refus hôte d'une demande : pending → cancelled (motif obligatoire).
     */
    public function refuse(Reservation $reservation, User $host, string $reason): void
    {
        if ($reservation->getStatus() !== self::PENDING) {
            throw new InvalidReservationTransitionException((string) $reservation->getStatus(), self::CANCELLED);
        }

        $this->transitionToCancelled($reservation, $host, $reason, ReservationNotificationType::REFUSED);
    }

    /**
     * Annulation (voyageur ou hôte) : pending|confirmed → cancelled (motif obligatoire).
     * La libération des dates est automatique (A.2 ne compte que les réservations confirmed).
     */
    public function cancel(Reservation $reservation, User $actor, string $reason): void
    {
        $this->assertTransitionAllowed((string) $reservation->getStatus(), self::CANCELLED);

        $this->transitionToCancelled($reservation, $actor, $reason, ReservationNotificationType::CANCELLED);
    }

    private function transitionToCancelled(
        Reservation $reservation,
        User $actor,
        string $reason,
        ReservationNotificationType $type,
    ): void {
        $from = (string) $reservation->getStatus();
        $reservation->setStatus(self::CANCELLED);
        $reservation->setCancellationReason($reason);
        $this->addHistory($reservation, $from, self::CANCELLED, $actor);
        $this->entityManager->flush();

        $this->dispatch($reservation, $type);
    }

    private function assertTransitionAllowed(string $from, string $to): void
    {
        if (!in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true)) {
            throw new InvalidReservationTransitionException($from, $to);
        }
    }

    private function addHistory(Reservation $reservation, ?string $old, string $new, User $changedBy): void
    {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($old);
        $history->setNewStatus($new);
        $history->setChangedBy($changedBy);
        $this->entityManager->persist($history);
        $reservation->addStatusHistory($history);
    }

    private function dispatch(Reservation $reservation, ReservationNotificationType $type): void
    {
        $this->bus->dispatch(new ReservationNotification((string) $reservation->getId(), $type));
    }
}
