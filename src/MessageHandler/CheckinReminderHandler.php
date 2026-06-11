<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\CheckinReminderMessage;
use App\Repository\ReservationRepository;
use App\Service\MailService;
use App\Service\NotificationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class CheckinReminderHandler
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private MailService $mailService,
        private NotificationService $notificationService,
    ) {
    }

    public function __invoke(CheckinReminderMessage $message): void
    {
        $reservation = $this->reservationRepository->find(Uuid::fromString($message->reservationId));
        if ($reservation === null) {
            return;
        }

        $this->mailService->sendCheckinReminderEmail($reservation);

        $property = $reservation->getProperty();
        $guest = $reservation->getGuest();

        if ($guest !== null && $property !== null) {
            $title = 'Rappel de check-in';
            $body = sprintf('Votre séjour à "%s" commence demain. Retrouvez vos informations d\'accès.', $property->getTitle());
            $this->notificationService->notify($guest, $title, $body, '/reservations/' . $reservation->getId());
        }
    }
}
