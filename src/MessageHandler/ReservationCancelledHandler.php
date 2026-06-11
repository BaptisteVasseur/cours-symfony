<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationCancelledMessage;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ReservationCancelledHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {}

    public function __invoke(ReservationCancelledMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);
        if ($reservation === null) {
            return;
        }

        $reservation = $this->reservationRepository->findOneForDetail($reservation) ?? $reservation;

        $guest = $reservation->getGuest();
        $host  = $reservation->getProperty()?->getHost();

        // Si c'est le voyageur qui annule → notifier l'hôte (et vice-versa)
        $recipients = match ($message->cancelledBy) {
            'guest' => array_filter([$host]),
            'host'  => array_filter([$guest]),
            default => array_filter([$guest, $host]),
        };

        $subject = 'Réservation annulée — ' . ($reservation->getProperty()?->getTitle() ?? '');

        foreach ($recipients as $recipient) {
            $email = (new TemplatedEmail())
                ->from('noreply@clone-airbnb.local')
                ->to((string) $recipient->getEmail())
                ->subject($subject)
                ->htmlTemplate('email/reservation_cancelled.html.twig')
                ->context(['reservation' => $reservation, 'cancelledBy' => $message->cancelledBy]);

            $this->mailer->send($email);
        }
    }
}
