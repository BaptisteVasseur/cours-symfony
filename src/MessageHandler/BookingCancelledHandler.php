<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingCancelledMessage;
use App\Repository\ReservationRepository;
use App\Service\MailService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class BookingCancelledHandler
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private MailService $mailService,
        private \App\Service\NotificationService $notificationService,
    ) {
    }

    public function __invoke(BookingCancelledMessage $message): void
    {
        $reservation = $this->reservationRepository->find(Uuid::fromString($message->reservationId));
        if ($reservation === null) {
            return;
        }

        $this->mailService->sendBookingCancelledEmails($reservation);

        $property = $reservation->getProperty();
        $guest = $reservation->getGuest();
        $host = $property?->getHost();

        // Determine who cancelled
        $actor = null;
        foreach ($reservation->getStatusHistory() as $history) {
            if ($history->getNewStatus() === 'cancelled') {
                $actor = $history->getChangedBy();
            }
        }

        $reason = $reservation->getCancellationReason() ?? 'non spécifié';

        if ($property !== null) {
            // If the actor is the guest, notify the host
            if ($actor !== null && $guest !== null && $actor->getId() === $guest->getId()) {
                if ($host !== null) {
                    $guestName = $guest->getProfile()?->getFirstName() ?? 'Le voyageur';
                    $title = 'Réservation annulée par le voyageur';
                    $body = sprintf('%s a annulé sa réservation pour "%s". Motif : %s', $guestName, $property->getTitle(), $reason);
                    $this->notificationService->notify($host, $title, $body, '/compte/hote/reservations');
                }
            } 
            // If the actor is the host, notify the guest
            elseif ($actor !== null && $host !== null && $actor->getId() === $host->getId()) {
                if ($guest !== null) {
                    $title = 'Réservation annulée par l\'hôte';
                    $body = sprintf('L\'hôte a annulé votre réservation pour "%s". Motif : %s', $property->getTitle(), $reason);
                    $this->notificationService->notify($guest, $title, $body, '/reservations/' . $reservation->getId());
                }
            } 
            // If cancelled by system or unknown actor, notify both
            else {
                if ($guest !== null) {
                    $title = 'Réservation annulée';
                    $body = sprintf('Votre réservation pour "%s" a été annulée. Motif : %s', $property->getTitle(), $reason);
                    $this->notificationService->notify($guest, $title, $body, '/reservations/' . $reservation->getId());
                }
                if ($host !== null) {
                    $title = 'Réservation annulée';
                    $body = sprintf('La réservation pour "%s" a été annulée. Motif : %s', $property->getTitle(), $reason);
                    $this->notificationService->notify($host, $title, $body, '/compte/hote/reservations');
                }
            }
        }
    }
}
