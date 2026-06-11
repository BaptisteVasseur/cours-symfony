<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\ReservationStatusHistory;
use App\Message\ExpireReservationMessage;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class ExpireReservationHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(ExpireReservationMessage $message): void
    {
        $reservation = $this->reservationRepository->find(Uuid::fromString($message->reservationId));

        if ($reservation === null || $reservation->getStatus() !== 'pending') {
            return;
        }

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus('pending');
        $history->setNewStatus('cancelled');
        $history->setChangedBy($reservation->getGuest());

        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason('Expiration automatique : demande non confirmée sous 24h.');
        $reservation->addStatusHistory($history);

        $this->entityManager->flush();
    }
}
