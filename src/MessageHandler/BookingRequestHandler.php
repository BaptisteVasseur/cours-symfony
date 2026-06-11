<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Mail\BookingMailer;
use App\Message\BookingRequestMessage;
use App\Repository\ReservationRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class BookingRequestHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly BookingMailer $mailer,
    ) {}

    public function __invoke(BookingRequestMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);

        if ($reservation === null) {
            return;
        }

        $this->mailer->sendBookingRequest($reservation);
    }
}
