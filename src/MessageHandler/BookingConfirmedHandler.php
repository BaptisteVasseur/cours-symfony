<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingConfirmedMessage;
use App\Repository\ReservationRepository;
use App\Service\MailService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class BookingConfirmedHandler
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private MailService $mailService,
    ) {
    }

    public function __invoke(BookingConfirmedMessage $message): void
    {
        $reservation = $this->reservationRepository->find(Uuid::fromString($message->reservationId));
        if ($reservation === null) {
            return;
        }

        $this->mailService->sendBookingConfirmationEmails($reservation);
    }
}
