<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationCancelledMessage;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ReservationCancelledHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly UserRepository $userRepository,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(ReservationCancelledMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);
        if ($reservation === null) {
            $this->logger->warning('ReservationCancelledHandler: reservation not found', ['id' => $message->reservationId]);
            return;
        }

        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();
        $property = $reservation->getProperty();
        $reason = $reservation->getCancellationReason() ?? '';
        $context = ['reservation' => $reservation, 'reason' => $reason];

        $cancelledBy = $this->userRepository->find($message->cancelledByUserId);
        $cancelledByHost = $cancelledBy !== null && $cancelledBy->getId() === $host?->getId();

        if ($guest !== null) {
            $this->notificationService->sendEmail(
                $guest,
                'Votre réservation a été annulée',
                'emails/reservation_cancelled_guest.html.twig',
                $context,
            );
            $this->notificationService->createInApp(
                $guest,
                'reservation_cancelled',
                'Réservation annulée',
                sprintf(
                    'Votre réservation pour "%s" a été annulée. Motif : %s',
                    $property?->getTitle() ?? '',
                    $reason,
                ),
            );
        }

        if ($host !== null && !$cancelledByHost) {
            $this->notificationService->sendEmail(
                $host,
                'Une réservation a été annulée',
                'emails/reservation_cancelled_host.html.twig',
                $context,
            );
            $this->notificationService->createInApp(
                $host,
                'reservation_cancelled',
                'Réservation annulée',
                sprintf(
                    'La réservation pour "%s" du %s au %s a été annulée par le voyageur.',
                    $property?->getTitle() ?? '',
                    $reservation->getCheckinDate()->format('d/m/Y'),
                    $reservation->getCheckoutDate()->format('d/m/Y'),
                ),
            );
        }

        $this->em->flush();
    }
}
