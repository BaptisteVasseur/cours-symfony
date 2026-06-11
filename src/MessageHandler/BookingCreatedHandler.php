<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingCreatedMessage;
use App\Repository\ReservationRepository;
use App\Service\MailService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class BookingCreatedHandler
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private MailService $mailService,
    ) {
    }

    public function __invoke(BookingCreatedMessage $message): void
    {
        $reservation = $this->reservationRepository->find(Uuid::fromString($message->reservationId));
        if ($reservation === null) {
            return;
        }

        $this->mailService->sendBookingPendingHostEmail($reservation);
    }
}
