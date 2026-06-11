<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationConfirmedMessage;
use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ReservationConfirmedHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(ReservationConfirmedMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);
        if ($reservation === null) {
            $this->logger->warning('ReservationConfirmedHandler: reservation not found', ['id' => $message->reservationId]);
            return;
        }

        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();
        $property = $reservation->getProperty();
        $context = ['reservation' => $reservation];

        if ($guest !== null) {
            $this->notificationService->sendEmail(
                $guest,
                'Votre réservation est confirmée !',
                'emails/reservation_confirmed_guest.html.twig',
                $context,
            );
            $this->notificationService->createInApp(
                $guest,
                'reservation_confirmed',
                'Réservation confirmée',
                sprintf(
                    'Votre réservation pour "%s" du %s au %s est confirmée.',
                    $property?->getTitle() ?? '',
                    $reservation->getCheckinDate()->format('d/m/Y'),
                    $reservation->getCheckoutDate()->format('d/m/Y'),
                ),
            );
        }

        if ($host !== null) {
            $this->notificationService->sendEmail(
                $host,
                'Réservation confirmée',
                'emails/reservation_confirmed_host.html.twig',
                $context,
            );
        }

        $this->em->flush();
    }
}
