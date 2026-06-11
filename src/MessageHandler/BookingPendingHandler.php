<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingPendingMessage;
use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class BookingPendingHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function __invoke(BookingPendingMessage $message): void
    {
        $reservation = $this->reservationRepository->find(Uuid::fromString($message->reservationId));
        if ($reservation === null) {
            return;
        }

        $host = $reservation->getProperty()?->getHost();
        if ($host === null) {
            return;
        }

        $this->notificationService->notifyBookingPending($reservation);

        $email = (new TemplatedEmail())
            ->to($host->getEmail())
            ->subject('Nouvelle demande de réservation — '.$reservation->getProperty()?->getTitle())
            ->htmlTemplate('emails/booking_pending.html.twig')
            ->context(['reservation' => $reservation]);

        $this->mailer->send($email);
    }
}
