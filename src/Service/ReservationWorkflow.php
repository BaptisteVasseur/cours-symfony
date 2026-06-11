<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Message\ReservationNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Orchestre les transitions d'état d'une réservation (pending → confirmed →
 * completed / cancelled), trace l'historique et déclenche les notifications
 * asynchrones APRÈS le flush() de l'ORM : l'e-mail ne part que si la donnée
 * est bien persistée, et la base n'attend pas le réseau.
 */
final readonly class ReservationWorkflow
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private BookingService $bookingService,
        private NotificationService $notificationService,
    ) {
    }

    public const RESULT_OK = 'ok';
    public const RESULT_INVALID_STATE = 'invalid_state';
    public const RESULT_UNAVAILABLE = 'unavailable';

    /**
     * Confirme une demande "pending" (paiement voyageur ou acceptation hôte).
     * Revérifie qu'aucune autre réservation n'a pris le créneau entre-temps.
     */
    public function confirm(Reservation $reservation, User $actor): string
    {
        if ($reservation->getStatus() !== 'pending') {
            return self::RESULT_INVALID_STATE;
        }

        $unavailable = $this->bookingService->checkRangeAvailability(
            $reservation->getProperty(),
            $reservation->getCheckinDate(),
            $reservation->getCheckoutDate(),
            $reservation->getGuestsCount() ?? 1,
            $reservation,
        );
        if ($unavailable !== null) {
            return self::RESULT_UNAVAILABLE;
        }

        $this->transition($reservation, 'confirmed', $actor);
        $this->entityManager->flush();
        $this->notify($reservation, ReservationNotification::EVENT_CONFIRMED);

        return self::RESULT_OK;
    }

    /**
     * Refus d'une demande "pending" par l'hôte (motif obligatoire).
     */
    public function refuse(Reservation $reservation, User $actor, string $reason): string
    {
        if ($reservation->getStatus() !== 'pending') {
            return self::RESULT_INVALID_STATE;
        }

        $reservation->setCancellationReason($reason);
        $this->transition($reservation, 'cancelled', $actor);
        $this->entityManager->flush();
        $this->notify($reservation, ReservationNotification::EVENT_REFUSED);

        return self::RESULT_OK;
    }

    /**
     * Annulation d'une réservation confirmée (hôte ou voyageur). Les dates sont
     * libérées immédiatement : la disponibilité étant dérivée des réservations
     * bloquantes, passer en "cancelled" suffit à rouvrir le créneau.
     */
    public function cancel(Reservation $reservation, User $actor, string $reason): string
    {
        if (!in_array($reservation->getStatus(), ['confirmed', 'pending'], true)) {
            return self::RESULT_INVALID_STATE;
        }

        $reservation->setCancellationReason($reason);
        $this->transition($reservation, 'cancelled', $actor);
        $this->entityManager->flush();
        $this->notify($reservation, ReservationNotification::EVENT_CANCELLED);

        return self::RESULT_OK;
    }

    /**
     * Notifie l'hôte d'une nouvelle demande (appelé après création côté tunnel).
     */
    public function notifyRequested(Reservation $reservation): void
    {
        $this->notify($reservation, ReservationNotification::EVENT_REQUESTED);
    }

    /**
     * Enregistre l'état initial d'une réservation fraîchement créée, afin que
     * la timeline (G.5) parte du premier statut.
     */
    public function recordCreation(Reservation $reservation, User $actor): void
    {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus(null);
        $history->setNewStatus($reservation->getStatus() ?? 'pending');
        $history->setChangedBy($actor);
        $reservation->addStatusHistory($history);

        $this->entityManager->persist($history);
        $this->entityManager->flush();
    }

    /**
     * Expire la demande instantanée dont le verrou de paiement de 15 min est
     * dépassé. Appelé paresseusement à l'affichage du récapitulatif et par le
     * worker d'expiration (G.1).
     */
    public function expireStalePaymentLock(Reservation $reservation): bool
    {
        if (!$this->bookingService->isPaymentLockExpired($reservation)) {
            return false;
        }

        return $this->expirePending($reservation, sprintf(
            'Annulation automatique : paiement non effectué dans le délai de %d minutes.',
            HostCalendar::PENDING_LOCK_MINUTES,
        ));
    }

    /**
     * Expiration automatique (système) d'une demande "pending" : verrou de
     * paiement de 15 min dépassé (réservation instantanée) ou demande non
     * traitée par l'hôte après le délai (sur demande). Voir ExpirePendingReservationsCommand
     * pour l'invocation par le worker/cron (G.1).
     */
    public function expirePending(Reservation $reservation, string $reason): bool
    {
        if ($reservation->getStatus() !== 'pending') {
            return false;
        }

        $reservation->setCancellationReason($reason);
        $this->transition($reservation, 'cancelled', null);
        $this->entityManager->flush();
        $this->notify($reservation, ReservationNotification::EVENT_CANCELLED);

        return true;
    }

    private function transition(Reservation $reservation, string $newStatus, ?User $actor): void
    {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($reservation->getStatus());
        $history->setNewStatus($newStatus);
        $history->setChangedBy($actor);

        $reservation->addStatusHistory($history);
        $reservation->setStatus($newStatus);

        $this->entityManager->persist($history);
    }

    private function notify(Reservation $reservation, string $event): void
    {
        $id = $reservation->getId();
        if ($id === null) {
            return;
        }

        // In-app (synchrone, visible immédiatement) + e-mail (asynchrone).
        $this->notificationService->notifyReservation($reservation, $event);
        $this->messageBus->dispatch(new ReservationNotification((string) $id, $event));
    }
}
