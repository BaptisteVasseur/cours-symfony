<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingCreatedMessage;
use App\Repository\BookingRepository;
use App\Service\MailService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class BookingCreatedHandler
{
    public function __construct(
        private BookingRepository $bookingRepository,
        private MailService $mailService,
        private \App\Service\NotificationService $notificationService,
    ) {
    }

    public function __invoke(BookingCreatedMessage $message): void
    {
        $reservation = $this->bookingRepository->find(Uuid::fromString($message->reservationId));
        if ($reservation === null) {
            return;
        }

        $this->mailService->sendBookingPendingHostEmail($reservation);

        $property = $reservation->getProperty();
        $host = $property?->getHost();
        if ($host !== null) {
            $guestName = $reservation->getGuest()?->getProfile()?->getFirstName() ?? 'Un voyageur';
            $title = 'Nouvelle demande de réservation';
            $body = sprintf('%s souhaite réserver votre logement "%s".', $guestName, $property->getTitle());
            $this->notificationService->notify($host, $title, $body, '/compte/hote/reservations');
        }
    }
}
