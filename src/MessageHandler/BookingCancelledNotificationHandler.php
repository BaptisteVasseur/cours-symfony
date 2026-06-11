<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingCancelledNotification;
use App\Repository\ReservationRepository;
use App\Service\ReservationMailer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class BookingCancelledNotificationHandler
{
    public function __construct(
        private readonly ReservationRepository $reservations,
        private readonly ReservationMailer $mailer,
    ) {
    }

    public function __invoke(BookingCancelledNotification $message): void
    {
        $reservation = $this->reservations->find($message->reservationId);
        if ($reservation === null) {
            return;
        }

        $this->mailer->sendCancelled($reservation);
    }
}
