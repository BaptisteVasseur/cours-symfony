<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\NewReservationMessage;
use App\Repository\ReservationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

#[AsMessageHandler]
final class NewReservationHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {}

    public function __invoke(NewReservationMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);
        if ($reservation === null) {
            return;
        }

        $reservation = $this->reservationRepository->findOneForDetail($reservation) ?? $reservation;

        $host = $reservation->getProperty()?->getHost();
        if ($host === null || $reservation->getProperty() === null) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from('noreply@clone-airbnb.local')
            ->to((string) $host->getEmail())
            ->subject('Nouvelle demande de réservation — ' . $reservation->getProperty()->getTitle())
            ->htmlTemplate('email/new_reservation.html.twig')
            ->context(['reservation' => $reservation]);

        $this->mailer->send($email);
    }
}
