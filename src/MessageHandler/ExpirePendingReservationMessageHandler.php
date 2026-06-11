<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ExpirePendingReservationMessage;
use App\Repository\ReservationRepository;
use App\Service\Booking\ReservationStatusManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ExpirePendingReservationMessageHandler
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private ReservationStatusManager $reservationStatusManager,
    ) {
    }

    public function __invoke(ExpirePendingReservationMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->getReservationId());
        if ($reservation === null) {
            return;
        }

        $this->reservationStatusManager->expirePending(
            $reservation,
            'La demande a expire apres 24h sans reponse de l\'hote.',
        );
    }
}
