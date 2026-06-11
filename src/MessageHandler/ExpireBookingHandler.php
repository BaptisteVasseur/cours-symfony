<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingCancelledMessage;
use App\Message\ExpireBookingMessage;
use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use App\Service\ReservationWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class ExpireBookingHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly ReservationWorkflowService $workflowService,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {}

    public function __invoke(ExpireBookingMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);

        if ($reservation === null || $reservation->getStatus() !== 'pending') {
            return;
        }

        $host = $reservation->getProperty()?->getHost();
        if ($host === null) {
            return;
        }

        $this->workflowService->transition(
            $reservation,
            'cancelled',
            $host,
            'Expiration automatique — demande non traitée sous 24h.'
        );

        $this->notificationService->notifyBookingCancelled($reservation);
        $this->em->flush();

        $this->bus->dispatch(new BookingCancelledMessage((string) $reservation->getId()));
    }
}
