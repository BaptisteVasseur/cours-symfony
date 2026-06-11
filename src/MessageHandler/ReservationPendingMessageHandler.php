<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationPendingMessage;
use App\Repository\ReservationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class ReservationPendingMessageHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(ReservationPendingMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);

        if ($reservation === null) {
            return;
        }

        $guest = $reservation->getGuest();
        $property = $reservation->getProperty();
        $host = $property->getHost();

        $emailHost = (new Email())
            ->from('noreply@airbnb-clone.local')
            ->to($host->getEmail())
            ->subject('Nouvelle demande de réservation')
            ->html(sprintf(
                '<h1>Nouvelle demande</h1>
                <p>%s souhaite réserver <strong>%s</strong> du %s au %s.</p>
                <p>Montant : %s €</p>
                <p><a href="/host/reservations/%s">Voir la demande</a></p>',
                $guest->getEmail(),
                $property->getTitle(),
                $reservation->getCheckinDate()->format('d/m/Y'),
                $reservation->getCheckoutDate()->format('d/m/Y'),
                $reservation->getTotalPrice(),
                $reservation->getId(),
            ));

        $emailGuest = (new Email())
            ->from('noreply@airbnb-clone.local')
            ->to($guest->getEmail())
            ->subject('Demande de réservation envoyée')
            ->html(sprintf(
                '<h1>Demande envoyée</h1>
                <p>Votre demande pour <strong>%s</strong> du %s au %s a bien été envoyée.</p>
                <p>L\'hôte va traiter votre demande prochainement.</p>',
                $property->getTitle(),
                $reservation->getCheckinDate()->format('d/m/Y'),
                $reservation->getCheckoutDate()->format('d/m/Y'),
            ));

        $this->mailer->send($emailHost);
        $this->mailer->send($emailGuest);
    }
}