<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Reservation;
use App\Message\ReservationNotification;
use App\Repository\ReservationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final class ReservationNotificationHandler
{
    private const string FROM = 'no-reply@clone-airbnb.local';

    public function __construct(
        private readonly ReservationRepository $reservations,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(ReservationNotification $message): void
    {
        $reservation = $this->reservations->find($message->reservationId);
        if ($reservation === null) {
            return;
        }

        match ($message->event) {
            ReservationNotification::PENDING_REQUEST => $this->sendPendingRequest($reservation),
            ReservationNotification::CONFIRMED => $this->sendConfirmation($reservation),
            ReservationNotification::REFUSED => $this->sendRefusal($reservation),
            ReservationNotification::CANCELLED => $this->sendCancellation($reservation),
            default => null,
        };
    }

    private function sendPendingRequest(Reservation $reservation): void
    {
        $host = $reservation->getProperty()?->getHost()?->getEmail();
        if ($host === null) {
            return;
        }

        $this->send(
            $host,
            'Nouvelle demande de réservation',
            sprintf(
                "Vous avez une nouvelle demande pour %s.\n%s\nVoyageur : %s\nTotal : %s %s\n\nConnectez-vous à votre espace hôte pour accepter ou refuser.",
                $this->propertyTitle($reservation),
                $this->stayLine($reservation),
                $reservation->getGuest()?->getEmail() ?? 'inconnu',
                $reservation->getTotalPrice(),
                $reservation->getCurrency(),
            ),
        );
    }

    private function sendConfirmation(Reservation $reservation): void
    {
        $stay = sprintf(
            "%s\n%s\nTotal : %s %s",
            $this->propertyTitle($reservation),
            $this->stayLine($reservation),
            $reservation->getTotalPrice(),
            $reservation->getCurrency(),
        );

        $guest = $reservation->getGuest()?->getEmail();
        if ($guest !== null) {
            $this->send($guest, 'Votre réservation est confirmée', "Votre réservation est confirmée.\n\n" . $stay);
        }

        $host = $reservation->getProperty()?->getHost()?->getEmail();
        if ($host !== null) {
            $this->send($host, 'Une réservation a été confirmée', "Une réservation a été confirmée sur votre logement.\n\n" . $stay);
        }
    }

    private function sendRefusal(Reservation $reservation): void
    {
        $guest = $reservation->getGuest()?->getEmail();
        if ($guest === null) {
            return;
        }

        $this->send(
            $guest,
            'Votre demande de réservation a été refusée',
            sprintf(
                "Votre demande pour %s a été refusée.\n%s\nMotif : %s",
                $this->propertyTitle($reservation),
                $this->stayLine($reservation),
                $reservation->getCancellationReason() ?? 'non précisé',
            ),
        );
    }

    private function sendCancellation(Reservation $reservation): void
    {
        $body = sprintf(
            "La réservation pour %s a été annulée.\n%s\nMotif : %s",
            $this->propertyTitle($reservation),
            $this->stayLine($reservation),
            $reservation->getCancellationReason() ?? 'non précisé',
        );

        foreach ([
            $reservation->getGuest()?->getEmail(),
            $reservation->getProperty()?->getHost()?->getEmail(),
        ] as $recipient) {
            if ($recipient !== null) {
                $this->send($recipient, 'Réservation annulée', $body);
            }
        }
    }

    private function send(string $to, string $subject, string $body): void
    {
        $this->mailer->send(
            (new Email())
                ->from(self::FROM)
                ->to($to)
                ->subject($subject)
                ->text($body),
        );
    }

    private function propertyTitle(Reservation $reservation): string
    {
        return $reservation->getProperty()?->getTitle() ?? 'votre logement';
    }

    private function stayLine(Reservation $reservation): string
    {
        return sprintf(
            'Du %s au %s pour %d voyageur(s)',
            $reservation->getCheckinDate()?->format('d/m/Y') ?? '',
            $reservation->getCheckoutDate()?->format('d/m/Y') ?? '',
            $reservation->getGuestsCount() ?? 0,
        );
    }
}
