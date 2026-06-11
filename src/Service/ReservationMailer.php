<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class ReservationMailer
{
    private const FROM = 'no-reply@clone-airbnb.local';

    public function __construct(
        private readonly MailerInterface $mailer,
    ) {
    }

    public function sendRequested(Reservation $reservation): void
    {
        $host = $reservation->getProperty()?->getHost();
        if ($host === null) {
            return;
        }

        $this->send(
            $host->getEmail(),
            'Nouvelle demande de réservation',
            'email/booking_requested.html.twig',
            $reservation,
        );
    }

    public function sendConfirmed(Reservation $reservation): void
    {
        foreach ($this->bothParties($reservation) as $recipient) {
            $this->send(
                $recipient,
                'Réservation confirmée',
                'email/booking_confirmed.html.twig',
                $reservation,
            );
        }
    }

    public function sendRejected(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        if ($guest === null) {
            return;
        }

        $this->send(
            $guest->getEmail(),
            'Votre demande de réservation a été refusée',
            'email/booking_rejected.html.twig',
            $reservation,
        );
    }

    public function sendCancelled(Reservation $reservation): void
    {
        foreach ($this->bothParties($reservation) as $recipient) {
            $this->send(
                $recipient,
                'Réservation annulée',
                'email/booking_cancelled.html.twig',
                $reservation,
            );
        }
    }

    /**
     * @return list<string>
     */
    private function bothParties(Reservation $reservation): array
    {
        $recipients = [];
        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();

        if ($guest !== null && $guest->getEmail() !== null) {
            $recipients[] = $guest->getEmail();
        }
        if ($host !== null && $host->getEmail() !== null) {
            $recipients[] = $host->getEmail();
        }

        return $recipients;
    }

    private function send(?string $to, string $subject, string $template, Reservation $reservation): void
    {
        if ($to === null) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address(self::FROM, 'Clone Airbnb'))
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($template)
            ->context(['reservation' => $reservation]);

        $this->mailer->send($email);
    }
}
