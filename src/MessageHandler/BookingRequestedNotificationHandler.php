<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingRequestedNotification;
use App\Repository\ReservationRepository;
use App\Service\ReservationMailer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class BookingRequestedNotificationHandler
{
    public function __construct(
        private readonly ReservationRepository $reservations,
        private readonly ReservationMailer $mailer,
    ) {
    }

    public function __invoke(BookingRequestedNotification $message): void
    {
        $reservation = $this->reservations->find($message->reservationId);
        if ($reservation === null) {
            return;
        }

        $this->mailer->sendRequested($reservation);
    }
}
