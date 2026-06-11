<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingCancelledMessage;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class BookingCancelledHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly UserRepository $userRepository,
        private readonly MailerInterface $mailer,
        private readonly NotificationService $notificationService,
    ) {}

    public function __invoke(BookingCancelledMessage $message): void
    {
        $reservation = $this->reservationRepository->find(Uuid::fromString($message->reservationId));
        if ($reservation === null) {
            return;
        }

        $isSystem = $message->cancelledByUserId === 'system';
        $cancelledBy = null;

        if (!$isSystem) {
            $cancelledBy = $this->userRepository->find(Uuid::fromString($message->cancelledByUserId));
            if ($cancelledBy === null) {
                return;
            }
            $this->notificationService->notifyBookingCancelled($reservation, $cancelledBy, $message->reason);
        }

        $context = ['reservation' => $reservation, 'reason' => $message->reason];

        $guest = $reservation->getGuest();
        if ($guest !== null && ($isSystem || $guest->getId() !== $cancelledBy?->getId())) {
            $this->mailer->send(
                (new TemplatedEmail())
                    ->to($guest->getEmail())
                    ->subject('Réservation annulée — '.$reservation->getProperty()?->getTitle())
                    ->htmlTemplate('emails/booking_cancelled.html.twig')
                    ->context($context)
            );
        }

        $host = $reservation->getProperty()?->getHost();
        if ($host !== null && ($isSystem || $host->getId() !== $cancelledBy?->getId())) {
            $this->mailer->send(
                (new TemplatedEmail())
                    ->to($host->getEmail())
                    ->subject('Réservation annulée — '.$reservation->getProperty()?->getTitle())
                    ->htmlTemplate('emails/booking_cancelled.html.twig')
                    ->context($context)
            );
        }
    }
}
