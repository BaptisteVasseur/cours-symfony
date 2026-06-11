<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\AutoCancelPendingReservationMessage;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AutoCancelPendingReservationHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function __invoke(AutoCancelPendingReservationMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);

        if ($reservation === null) {
            return;
        }

        if ($reservation->getStatus() !== 'pending') {
            return;
        }

        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason('Expiration automatique : demande en attente depuis plus de 24 heures.');

        $this->entityManager->flush();
    }
}
