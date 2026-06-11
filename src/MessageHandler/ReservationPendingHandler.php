<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationPendingMessage;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final class ReservationPendingHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {}

    public function __invoke(ReservationPendingMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);
        if ($reservation === null) {
            return;
        }

        $host = $reservation->getProperty()?->getHost();
        if ($host === null || $host->getEmail() === null) {
            return;
        }

        $hostName = $host->getProfile()?->getFirstName() ?? $host->getEmail();

        $email = (new TemplatedEmail())
            ->to(new Address($host->getEmail(), $hostName))
            ->subject('Nouvelle demande de réservation - ' . $reservation->getProperty()?->getTitle())
            ->htmlTemplate('emails/reservation_pending.html.twig')
            ->context(['reservation' => $reservation]);

        $this->mailer->send($email);
    }
}