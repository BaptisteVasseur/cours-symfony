<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationConfirmedMessage;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ReservationConfirmedHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {}

    public function __invoke(ReservationConfirmedMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);
        if ($reservation === null) {
            return;
        }

        $reservation = $this->reservationRepository->findOneForDetail($reservation) ?? $reservation;

        $guest = $reservation->getGuest();
        $host  = $reservation->getProperty()?->getHost();

        if ($guest === null || $reservation->getProperty() === null) {
            return;
        }

        $subject = 'Réservation confirmée — ' . $reservation->getProperty()->getTitle();

        foreach (array_filter([$guest, $host]) as $recipient) {
            $email = (new TemplatedEmail())
                ->from('noreply@clone-airbnb.local')
                ->to((string) $recipient->getEmail())
                ->subject($subject)
                ->htmlTemplate('email/reservation_confirmed.html.twig')
                ->context(['reservation' => $reservation, 'recipient' => $recipient]);

            $this->mailer->send($email);
        }
    }
}
