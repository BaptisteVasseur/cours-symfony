<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationCancelledMessage;
use App\Repository\ReservationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class ReservationCancelledMessageHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(ReservationCancelledMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);

        if ($reservation === null) {
            return;
        }

        $guest = $reservation->getGuest();
        $property = $reservation->getProperty();
        $host = $property->getHost();
        $reason = $reservation->getCancellationReason() ?? 'Aucun motif fourni';

        $emailGuest = (new Email())
            ->from('noreply@airbnb-clone.local')
            ->to($guest->getEmail())
            ->subject('Réservation annulée')
            ->html(sprintf(
                '<h1>Réservation annulée</h1>
                <p>Votre réservation pour <strong>%s</strong> du %s au %s a été annulée.</p>
                <p>Motif : %s</p>',
                $property->getTitle(),
                $reservation->getCheckinDate()->format('d/m/Y'),
                $reservation->getCheckoutDate()->format('d/m/Y'),
                $reason,
            ));

        $emailHost = (new Email())
            ->from('noreply@airbnb-clone.local')
            ->to($host->getEmail())
            ->subject('Réservation annulée')
            ->html(sprintf(
                '<h1>Réservation annulée</h1>
                <p>La réservation de %s pour <strong>%s</strong> du %s au %s a été annulée.</p>
                <p>Motif : %s</p>',
                $guest->getEmail(),
                $property->getTitle(),
                $reservation->getCheckinDate()->format('d/m/Y'),
                $reservation->getCheckoutDate()->format('d/m/Y'),
                $reason,
            ));

        $this->mailer->send($emailGuest);
        $this->mailer->send($emailHost);
    }
}