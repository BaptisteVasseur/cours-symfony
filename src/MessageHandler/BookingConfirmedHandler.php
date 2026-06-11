<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingConfirmedMessage;
use App\Repository\BookingRepository;
use App\Service\MailService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class BookingConfirmedHandler
{
    public function __construct(
        private BookingRepository $bookingRepository,
        private MailService $mailService,
        private \App\Service\NotificationService $notificationService,
    ) {
    }

    public function __invoke(BookingConfirmedMessage $message): void
    {
        $reservation = $this->bookingRepository->find(Uuid::fromString($message->reservationId));
        if ($reservation === null) {
            return;
        }

        $this->mailService->sendBookingConfirmationEmails($reservation);

        $property = $reservation->getProperty();
        $guest = $reservation->getGuest();
        $host = $property?->getHost();

        if ($guest !== null && $property !== null) {
            $title = 'Séjour confirmé !';
            $body = sprintf('Votre séjour à "%s" du %s au %s a été confirmé.', $property->getTitle(), $reservation->getCheckinDate()?->format('d/m/Y'), $reservation->getCheckoutDate()?->format('d/m/Y'));
            $this->notificationService->notify($guest, $title, $body, '/reservations/' . $reservation->getId());
        }

        if ($host !== null && $property !== null) {
            $guestName = $guest?->getProfile()?->getFirstName() ?? 'Un voyageur';
            $title = 'Réservation confirmée';
            $body = sprintf('La réservation de %s pour votre logement "%s" est confirmée.', $guestName, $property->getTitle());
            $this->notificationService->notify($host, $title, $body, '/compte/hote/reservations');
        }
    }
}
