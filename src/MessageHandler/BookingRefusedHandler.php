<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingRefusedMessage;
use App\Repository\ReservationRepository;
use App\Service\MailService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class BookingRefusedHandler
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private MailService $mailService,
    ) {
    }

    public function __invoke(BookingRefusedMessage $message): void
    {
        $reservation = $this->reservationRepository->find(Uuid::fromString($message->reservationId));
        if ($reservation === null) {
            return;
        }

        $this->mailService->sendBookingRefusedEmail($reservation);
    }
}
