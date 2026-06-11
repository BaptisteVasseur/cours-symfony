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
        private \App\Service\NotificationService $notificationService,
    ) {
    }

    public function __invoke(BookingRefusedMessage $message): void
    {
        $reservation = $this->reservationRepository->find(Uuid::fromString($message->reservationId));
        if ($reservation === null) {
            return;
        }

        $this->mailService->sendBookingRefusedEmail($reservation);

        $property = $reservation->getProperty();
        $guest = $reservation->getGuest();
        if ($guest !== null && $property !== null) {
            $title = 'Demande de réservation refusée';
            $body = sprintf('Votre demande pour le logement "%s" a été refusée par l\'hôte. Motif : %s', $property->getTitle(), $reservation->getCancellationReason() ?? 'non spécifié');
            $this->notificationService->notify($guest, $title, $body, '/reservations/' . $reservation->getId());
        }
    }
}
