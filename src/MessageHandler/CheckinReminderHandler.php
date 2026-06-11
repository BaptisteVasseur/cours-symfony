<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Mail\BookingMailer;
use App\Message\CheckinReminderMessage;
use App\Repository\ReservationRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CheckinReminderHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly BookingMailer $mailer,
    ) {}

    public function __invoke(CheckinReminderMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);

        if ($reservation === null || $reservation->getStatus() !== 'confirmed') {
            return;
        }

        $this->mailer->sendCheckinReminder($reservation);
    }
}
