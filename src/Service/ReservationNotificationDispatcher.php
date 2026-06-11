<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use App\Message\ExpirePendingReservationMessage;
use App\Message\ReservationNotificationMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class ReservationNotificationDispatcher
{
    private const PENDING_EXPIRATION_DELAY_MS = 24 * 60 * 60 * 1000;

    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public function dispatchReservationCreated(Reservation $reservation): void
    {
        $reservationId = $reservation->getId();
        if ($reservationId === null) {
            return;
        }

        $reservationId = (string) $reservationId;

        if ($reservation->getStatus() === 'pending') {
            $this->messageBus->dispatch(new ReservationNotificationMessage(
                $reservationId,
                ReservationNotificationMessage::TYPE_REQUEST_CREATED,
            ));

            $this->messageBus->dispatch(
                new ExpirePendingReservationMessage($reservationId),
                [new DelayStamp(self::PENDING_EXPIRATION_DELAY_MS)],
            );

            return;
        }

        if ($reservation->getStatus() === 'confirmed') {
            $this->dispatchReservationConfirmed($reservation);
        }
    }

    public function dispatchReservationConfirmed(Reservation $reservation): void
    {
        $this->dispatch($reservation, ReservationNotificationMessage::TYPE_CONFIRMED);
    }

    public function dispatchReservationRejected(Reservation $reservation): void
    {
        $this->dispatch(
            $reservation,
            ReservationNotificationMessage::TYPE_REJECTED,
            ReservationNotificationMessage::INITIATOR_HOST,
        );
    }

    public function dispatchReservationCancelled(Reservation $reservation, string $initiator): void
    {
        $this->dispatch($reservation, ReservationNotificationMessage::TYPE_CANCELLED, $initiator);
    }

    private function dispatch(Reservation $reservation, string $type, ?string $initiator = null): void
    {
        $reservationId = $reservation->getId();
        if ($reservationId === null) {
            return;
        }

        $reservationId = (string) $reservationId;

        $this->messageBus->dispatch(new ReservationNotificationMessage(
            $reservationId,
            $type,
            $initiator,
        ));
    }
}
