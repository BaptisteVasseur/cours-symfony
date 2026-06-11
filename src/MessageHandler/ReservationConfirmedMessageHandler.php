<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationConfirmedMessage;
use App\Repository\ReservationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class ReservationConfirmedMessageHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(ReservationConfirmedMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);

        if ($reservation === null) {
            return;
        }

        $guest = $reservation->getGuest();
        $property = $reservation->getProperty();
        $host = $property->getHost();

        $emailGuest = (new Email())
            ->from('noreply@airbnb-clone.local')
            ->to($guest->getEmail())
            ->subject('Votre réservation est confirmée !')
            ->html(sprintf(
                '<h1>Réservation confirmée</h1>
                <p>Bonjour,</p>
                <p>Votre réservation pour <strong>%s</strong> du %s au %s est confirmée.</p>
                <p>Montant total : %s €</p>',
                $property->getTitle(),
                $reservation->getCheckinDate()->format('d/m/Y'),
                $reservation->getCheckoutDate()->format('d/m/Y'),
                $reservation->getTotalPrice(),
            ));

        $emailHost = (new Email())
            ->from('noreply@airbnb-clone.local')
            ->to($host->getEmail())
            ->subject('Nouvelle réservation confirmée')
            ->html(sprintf(
                '<h1>Nouvelle réservation</h1>
                <p>La réservation de %s pour <strong>%s</strong> du %s au %s est confirmée.</p>',
                $guest->getEmail(),
                $property->getTitle(),
                $reservation->getCheckinDate()->format('d/m/Y'),
                $reservation->getCheckoutDate()->format('d/m/Y'),
            ));

        $this->mailer->send($emailGuest);
        $this->mailer->send($emailHost);
    }
}