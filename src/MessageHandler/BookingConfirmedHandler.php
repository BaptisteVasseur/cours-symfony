<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Mail\BookingMailer;
use App\Message\BookingConfirmedMessage;
use App\Repository\ReservationRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class BookingConfirmedHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly BookingMailer $mailer,
    ) {}

    public function __invoke(BookingConfirmedMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);

        if ($reservation === null) {
            return;
        }

        $this->mailer->sendBookingConfirmed($reservation);
    }
}
