<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingRejectedNotification;
use App\Repository\ReservationRepository;
use App\Service\ReservationMailer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class BookingRejectedNotificationHandler
{
    public function __construct(
        private readonly ReservationRepository $reservations,
        private readonly ReservationMailer $mailer,
    ) {
    }

    public function __invoke(BookingRejectedNotification $message): void
    {
        $reservation = $this->reservations->find($message->reservationId);
        if ($reservation === null) {
            return;
        }

        $this->mailer->sendRejected($reservation);
    }
}
