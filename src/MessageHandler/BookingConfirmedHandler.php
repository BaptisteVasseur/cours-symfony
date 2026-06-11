<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingConfirmedMessage;
use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class BookingConfirmedHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
        private readonly NotificationService $notificationService,
    ) {}

    public function __invoke(BookingConfirmedMessage $message): void
    {
        $reservation = $this->reservationRepository->find(Uuid::fromString($message->reservationId));
        if ($reservation === null) {
            return;
        }

        $this->notificationService->notifyBookingConfirmed($reservation);

        $guest = $reservation->getGuest();
        if ($guest !== null) {
            $this->mailer->send(
                (new TemplatedEmail())
                    ->to($guest->getEmail())
                    ->subject('Votre réservation est confirmée — '.$reservation->getProperty()?->getTitle())
                    ->htmlTemplate('emails/booking_confirmed.html.twig')
                    ->context(['reservation' => $reservation])
            );
        }

        $host = $reservation->getProperty()?->getHost();
        if ($host !== null) {
            $this->mailer->send(
                (new TemplatedEmail())
                    ->to($host->getEmail())
                    ->subject('Réservation confirmée — '.$reservation->getProperty()?->getTitle())
                    ->htmlTemplate('emails/booking_confirmed_host.html.twig')
                    ->context(['reservation' => $reservation])
            );
        }
    }
}
