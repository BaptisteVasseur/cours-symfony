<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationRejectedNotification;
use App\Repository\ReservationRepository;
use App\Service\Mail\ReservationNotifier;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class ReservationRejectedNotificationHandler
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private ReservationNotifier $notifier,
    ) {
    }

    public function __invoke(ReservationRejectedNotification $message): void
    {
        $reservation = $this->reservationRepository->find(Uuid::fromString($message->getReservationId()));
        if ($reservation === null) {
            return;
        }

        $this->notifier->rejected($reservation);
    }
}
