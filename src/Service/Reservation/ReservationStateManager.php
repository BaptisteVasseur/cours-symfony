<?php

declare(strict_types=1);

namespace App\Service\Reservation;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Exception\InvalidReservationTransitionException;
use App\Message\ReservationCancelledNotification;
use App\Message\ReservationConfirmedNotification;
use App\Message\ReservationRejectedNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Orchestre le cycle de vie d'une réservation (énoncé §4). Point d'entrée unique
 * des transitions de statut : valide la transition, journalise l'historique
 * (ReservationStatusHistory) et persiste. Les notifications asynchrones sont
 * branchées ici en P4.
 *
 * Cycle de vie autorisé :
 *   pending   → confirmed | cancelled
 *   confirmed → completed | cancelled
 *   completed → (terminal)
 *   cancelled → (terminal)
 *
 * Annuler une réservation « confirmed » libère implicitement les dates : la
 * disponibilité étant dérivée des réservations « confirmed », aucun nettoyage
 * de calendrier n'est nécessaire.
 */
final class ReservationStateManager
{
    /** @var array<string, list<string>> */
    private const array TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /** L'hôte accepte une demande en attente. */
    public function confirm(Reservation $reservation, ?User $by = null): void
    {
        $this->transition($reservation, 'confirmed', $by);
        $this->messageBus->dispatch(new ReservationConfirmedNotification((string) $reservation->getId()));
    }

    /** L'hôte refuse une demande en attente (motif obligatoire). */
    public function reject(Reservation $reservation, string $reason, ?User $by = null): void
    {
        $this->transition($reservation, 'cancelled', $by, $reason);
        $this->messageBus->dispatch(new ReservationRejectedNotification((string) $reservation->getId()));
    }

    /** Annulation par le voyageur, l'hôte ou le système (motif obligatoire). */
    public function cancel(Reservation $reservation, string $reason, ?User $by = null): void
    {
        $this->transition($reservation, 'cancelled', $by, $reason);
        $this->messageBus->dispatch(new ReservationCancelledNotification((string) $reservation->getId()));
    }

    /** Le séjour est terminé. */
    public function complete(Reservation $reservation, ?User $by = null): void
    {
        $this->transition($reservation, 'completed', $by);
    }

    private function transition(Reservation $reservation, string $to, ?User $by, ?string $reason = null): void
    {
        $from = (string) $reservation->getStatus();

        if ($from === $to) {
            return; // idempotent : aucune action si le statut est déjà atteint
        }

        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new InvalidReservationTransitionException(
                sprintf('Transition de statut interdite : %s → %s.', $from, $to),
            );
        }

        if ($to === 'cancelled') {
            if (trim($reason ?? '') === '') {
                throw new InvalidReservationTransitionException('Un motif est obligatoire pour une annulation.');
            }
            $reservation->setCancellationReason($reason);
        }

        $reservation->setStatus($to);

        $history = (new ReservationStatusHistory())
            ->setReservation($reservation)
            ->setOldStatus($from)
            ->setNewStatus($to)
            ->setChangedBy($by);
        $reservation->addStatusHistory($history);

        $this->entityManager->persist($history);
        $this->entityManager->flush();

        // La notification asynchrone est dispatchée par la méthode publique
        // appelante, APRÈS ce flush (entité persistée → le handler la recharge).
    }
}
