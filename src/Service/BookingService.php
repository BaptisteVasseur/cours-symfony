<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Enum\ReservationNotificationType;
use App\Enum\ReservationStatus;
use App\Message\ReservationNotification;
use App\Service\Exception\InvalidReservationTransitionException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Orchestrates the reservation lifecycle: creation (with availability re-check) and the
 * pending → confirmed / cancelled transitions, recording every change in ReservationStatusHistory.
 *
 * Email notifications are dispatched (async) only after the transaction has committed, so the
 * handler can reload the persisted reservation.
 */
final class BookingService
{
    private const SERVICE_FEE_RATE = 0.12;
    private const CURRENCY = 'EUR';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AvailabilityService $availability,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * Creates a reservation after re-checking availability inside the transaction, guarding against
     * concurrent bookings of the same dates.
     */
    public function createBooking(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
    ): Reservation {
        $checkin = $checkin->setTime(0, 0);
        $checkout = $checkout->setTime(0, 0);

        $reservation = $this->em->wrapInTransaction(function () use ($property, $guest, $checkin, $checkout, $guests): Reservation {
            $this->availability->assertRangeAvailable($property, $checkin, $checkout, $guests);

            $status = $property->isInstantBooking() ? ReservationStatus::Confirmed : ReservationStatus::Pending;

            $reservation = new Reservation();
            $reservation->setProperty($property);
            $reservation->setGuest($guest);
            $reservation->setCheckinDate($checkin);
            $reservation->setCheckoutDate($checkout);
            $reservation->setGuestsCount($guests);
            $reservation->setStatus($status->value);
            $this->applyPricing($reservation, $property, $checkin, $checkout);

            $this->em->persist($reservation);
            $this->recordStatus($reservation, null, $status, $guest);
            $this->em->flush();

            return $reservation;
        });

        $this->notify($reservation, $reservation->getStatus() === ReservationStatus::Confirmed->value
            ? ReservationNotificationType::Confirmed
            : ReservationNotificationType::RequestReceived);

        return $reservation;
    }

    /**
     * Host accepts a pending request. Re-checks availability (a concurrent confirmation may have
     * taken the dates) before locking them.
     */
    public function confirm(Reservation $reservation, User $actor): void
    {
        $this->assertStatus($reservation, [ReservationStatus::Pending], 'Seule une demande en attente peut être acceptée.');

        $this->em->wrapInTransaction(function () use ($reservation, $actor): void {
            $this->availability->assertRangeAvailable(
                $reservation->getProperty(),
                $reservation->getCheckinDate(),
                $reservation->getCheckoutDate(),
                (int) $reservation->getGuestsCount(),
                exclude: $reservation,
            );

            $this->applyTransition($reservation, ReservationStatus::Confirmed, $actor);
            $this->em->flush();
        });

        $this->notify($reservation, ReservationNotificationType::Confirmed);
    }

    /**
     * Host refuses a pending request (requires a reason).
     */
    public function reject(Reservation $reservation, User $actor, string $reason): void
    {
        $this->assertStatus($reservation, [ReservationStatus::Pending], 'Seule une demande en attente peut être refusée.');
        $this->cancelWithReason($reservation, $actor, $reason);
        $this->notify($reservation, ReservationNotificationType::Rejected);
    }

    /**
     * Guest or host cancels a pending/confirmed reservation (requires a reason). Cancelling frees
     * the dates automatically because only confirmed reservations block the calendar.
     */
    public function cancel(Reservation $reservation, User $actor, string $reason): void
    {
        $this->assertStatus(
            $reservation,
            [ReservationStatus::Pending, ReservationStatus::Confirmed],
            'Cette réservation ne peut plus être annulée.',
        );
        $this->cancelWithReason($reservation, $actor, $reason);
        $this->notify($reservation, ReservationNotificationType::Cancelled);
    }

    private function cancelWithReason(Reservation $reservation, User $actor, string $reason): void
    {
        $this->em->wrapInTransaction(function () use ($reservation, $actor, $reason): void {
            $reservation->setCancellationReason($reason);
            $this->applyTransition($reservation, ReservationStatus::Cancelled, $actor);
            $this->em->flush();
        });
    }

    private function applyTransition(Reservation $reservation, ReservationStatus $to, User $actor): void
    {
        $from = $reservation->getStatus();
        $reservation->setStatus($to->value);
        $this->recordStatus($reservation, $from, $to, $actor);
    }

    private function notify(Reservation $reservation, ReservationNotificationType $type): void
    {
        $this->bus->dispatch(new ReservationNotification((string) $reservation->getId(), $type));
    }

    private function recordStatus(Reservation $reservation, ?string $old, ReservationStatus $new, User $actor): void
    {
        $history = new ReservationStatusHistory();
        $history->setOldStatus($old);
        $history->setNewStatus($new->value);
        $history->setChangedBy($actor);
        $reservation->addStatusHistory($history);

        $this->em->persist($history);
    }

    /**
     * @param list<ReservationStatus> $allowed
     */
    private function assertStatus(Reservation $reservation, array $allowed, string $message): void
    {
        $current = array_map(static fn (ReservationStatus $s): string => $s->value, $allowed);

        if (!in_array($reservation->getStatus(), $current, true)) {
            throw new InvalidReservationTransitionException($message);
        }
    }

    private function applyPricing(
        Reservation $reservation,
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): void {
        $nights = (int) $checkin->diff($checkout)->days;
        $subtotal = (float) $property->getPricePerNight() * $nights;
        $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
        $serviceFee = round($subtotal * self::SERVICE_FEE_RATE, 2);

        $reservation->setTotalPrice((string) round($subtotal + $cleaningFee + $serviceFee, 2));
        $reservation->setCleaningFee($cleaningFee > 0 ? (string) $cleaningFee : null);
        $reservation->setServiceFee((string) $serviceFee);
        $reservation->setSecurityDeposit($property->getSecurityDeposit());
        $reservation->setCurrency(self::CURRENCY);
    }
}
