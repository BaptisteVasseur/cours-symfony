<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Message\ReservationNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Orchestration du cycle de vie d'une réservation : création, confirmation, refus,
 * annulation, complétion. Chaque transition est tracée dans l'historique des statuts.
 *
 * Transitions autorisées :
 *   pending   -> confirmed | cancelled
 *   confirmed -> cancelled | completed
 */
final readonly class BookingService
{
    private const TRANSITIONS = [
        Reservation::STATUS_PENDING => [Reservation::STATUS_CONFIRMED, Reservation::STATUS_CANCELLED],
        Reservation::STATUS_CONFIRMED => [Reservation::STATUS_CANCELLED, Reservation::STATUS_COMPLETED],
        Reservation::STATUS_CANCELLED => [],
        Reservation::STATUS_COMPLETED => [],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private AvailabilityService $availability,
        private PricingService $pricing,
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * Crée une réservation après contrôle de disponibilité.
     * Statut confirmed si réservation instantanée, sinon pending.
     */
    public function create(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
    ): Reservation {
        $checkin = $checkin->setTime(0, 0, 0);
        $checkout = $checkout->setTime(0, 0, 0);

        $check = $this->availability->check($property, $checkin, $checkout, $guests);
        if (!$check->available) {
            throw new BookingException(implode(' ', $check->reasons));
        }

        $price = $this->pricing->compute($property, $checkin, $checkout);
        $status = $property->isInstantBooking() ? Reservation::STATUS_CONFIRMED : Reservation::STATUS_PENDING;

        $reservation = (new Reservation())
            ->setProperty($property)
            ->setGuest($guest)
            ->setCheckinDate($checkin)
            ->setCheckoutDate($checkout)
            ->setGuestsCount($guests)
            ->setStatus($status)
            ->setTotalPrice($price->total)
            ->setCleaningFee($price->cleaningFee)
            ->setServiceFee($price->serviceFee)
            ->setSecurityDeposit($price->securityDeposit)
            ->setCurrency($price->currency);

        $this->em->persist($reservation);
        $this->recordHistory($reservation, null, $status, $guest);
        $this->em->flush();

        $this->notify(
            $reservation,
            $status === Reservation::STATUS_CONFIRMED
                ? ReservationNotification::EVENT_CONFIRMED
                : ReservationNotification::EVENT_NEW_REQUEST,
        );

        return $reservation;
    }

    /**
     * Acceptation d'une demande par l'hôte. Re-contrôle la disponibilité (concurrence).
     */
    public function confirm(Reservation $reservation, User $host): Reservation
    {
        $this->guardTransition($reservation, Reservation::STATUS_CONFIRMED);

        $check = $this->availability->check(
            $reservation->getProperty(),
            $reservation->getCheckinDate(),
            $reservation->getCheckoutDate(),
            $reservation->getGuestsCount(),
            $reservation,
        );
        if (!$check->available) {
            throw new BookingException(implode(' ', $check->reasons));
        }

        $this->transition($reservation, Reservation::STATUS_CONFIRMED, $host);
        $this->notify($reservation, ReservationNotification::EVENT_CONFIRMED);

        return $reservation;
    }

    /**
     * Refus d'une demande par l'hôte (motif obligatoire).
     */
    public function refuse(Reservation $reservation, User $host, string $reason): Reservation
    {
        $this->guardTransition($reservation, Reservation::STATUS_CANCELLED);
        $reservation->setCancellationReason($this->requireReason($reason));

        $this->transition($reservation, Reservation::STATUS_CANCELLED, $host);
        $this->notify($reservation, ReservationNotification::EVENT_REFUSED);

        return $reservation;
    }

    /**
     * Annulation par l'hôte ou le voyageur (motif obligatoire). Libère les dates.
     */
    public function cancel(Reservation $reservation, User $actor, string $reason): Reservation
    {
        $this->guardTransition($reservation, Reservation::STATUS_CANCELLED);
        $reservation->setCancellationReason($this->requireReason($reason));

        $this->transition($reservation, Reservation::STATUS_CANCELLED, $actor);
        $this->notify($reservation, ReservationNotification::EVENT_CANCELLED);

        return $reservation;
    }

    public function complete(Reservation $reservation, User $actor): Reservation
    {
        $this->guardTransition($reservation, Reservation::STATUS_COMPLETED);

        return $this->transition($reservation, Reservation::STATUS_COMPLETED, $actor);
    }

    private function transition(Reservation $reservation, string $newStatus, User $actor): Reservation
    {
        $oldStatus = $reservation->getStatus();
        $reservation->setStatus($newStatus);
        $this->recordHistory($reservation, $oldStatus, $newStatus, $actor);
        $this->em->flush();

        return $reservation;
    }

    private function guardTransition(Reservation $reservation, string $newStatus): void
    {
        $current = $reservation->getStatus();
        if (!in_array($newStatus, self::TRANSITIONS[$current] ?? [], true)) {
            throw new BookingException(sprintf('Transition %s -> %s interdite.', $current, $newStatus));
        }
    }

    private function requireReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new BookingException('Un motif est obligatoire.');
        }

        return $reason;
    }

    /**
     * Dispatch de la notification APRÈS le flush : l'email ne part que si l'état est persisté.
     */
    private function notify(Reservation $reservation, string $event): void
    {
        $this->bus->dispatch(new ReservationNotification((string) $reservation->getId(), $event));
    }

    private function recordHistory(Reservation $reservation, ?string $old, string $new, User $actor): void
    {
        $history = (new ReservationStatusHistory())
            ->setReservation($reservation)
            ->setOldStatus($old)
            ->setNewStatus($new)
            ->setChangedBy($actor);

        $reservation->addStatusHistory($history);
        $this->em->persist($history);
    }
}
