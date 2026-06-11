<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationRequestedNotification;
use App\Repository\ReservationRepository;
use App\Service\Mail\ReservationNotifier;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class ReservationRequestedNotificationHandler
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private ReservationNotifier $notifier,
    ) {
    }

    public function __invoke(ReservationRequestedNotification $message): void
    {
        $reservation = $this->reservationRepository->find(Uuid::fromString($message->getReservationId()));
        if ($reservation === null) {
            return; // réservation supprimée entre-temps : rien à notifier
        }

        $this->notifier->requested($reservation);
    }
}
