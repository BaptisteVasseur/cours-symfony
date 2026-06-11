<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationCreatedMessage;
use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ReservationCreatedHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(ReservationCreatedMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);
        if ($reservation === null) {
            $this->logger->warning('ReservationCreatedHandler: reservation not found', ['id' => $message->reservationId]);
            return;
        }

        $host = $reservation->getProperty()?->getHost();
        $guest = $reservation->getGuest();

        if ($reservation->getStatus() === 'pending' && $host !== null) {
            // Notify host of new pending request
            $this->notificationService->sendEmail(
                $host,
                'Nouvelle demande de réservation',
                'emails/reservation_created_host.html.twig',
                ['reservation' => $reservation],
            );
            $this->notificationService->createInApp(
                $host,
                'reservation_request',
                'Nouvelle demande de réservation',
                sprintf(
                    '%s souhaite réserver "%s" du %s au %s.',
                    $guest?->getEmail() ?? 'Un voyageur',
                    $reservation->getProperty()?->getTitle() ?? '',
                    $reservation->getCheckinDate()->format('d/m/Y'),
                    $reservation->getCheckoutDate()->format('d/m/Y'),
                ),
            );
        } elseif ($reservation->getStatus() === 'confirmed' && $guest !== null) {
            // InstantBooking path: also send confirmation to guest immediately
            $this->notificationService->sendEmail(
                $guest,
                'Votre réservation est confirmée !',
                'emails/reservation_confirmed_guest.html.twig',
                ['reservation' => $reservation],
            );
            $this->notificationService->createInApp(
                $guest,
                'reservation_confirmed',
                'Réservation confirmée',
                sprintf(
                    'Votre réservation pour "%s" du %s au %s est confirmée.',
                    $reservation->getProperty()?->getTitle() ?? '',
                    $reservation->getCheckinDate()->format('d/m/Y'),
                    $reservation->getCheckoutDate()->format('d/m/Y'),
                ),
            );
        }

        $this->em->flush();
    }
}
