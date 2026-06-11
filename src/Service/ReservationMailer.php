<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class ReservationMailer
{
    private const FROM_EMAIL = 'no-reply@clone-airbnb.local';
    private const FROM_NAME = 'Clone Airbnb';

    public function __construct(
        private readonly MailerInterface $mailer,
    ) {
    }

    public function sendForNewReservation(Reservation $reservation): void
    {
        $property = $reservation->getProperty();
        $guest = $reservation->getGuest();
        $host = $property?->getHost();

        if ($property === null || $guest === null || $host === null) {
            return;
        }

        $context = [
            'reservation' => $reservation,
            'property' => $property,
            'guest' => $guest,
            'host' => $host,
        ];

        if ('confirmed' === $reservation->getStatus()) {
            // Réservation validée -> voyageur ET hôte
            $this->send((string) $guest->getEmail(), 'Votre réservation est confirmée', 'email/reservation_confirmed.html.twig', $context);
            $this->send((string) $host->getEmail(), 'Nouvelle réservation sur votre logement', 'email/reservation_confirmed.html.twig', $context);
        } else {
            // Demande en attente -> hôte uniquement
            $this->send((string) $host->getEmail(), 'Nouvelle demande de réservation', 'email/reservation_request.html.twig', $context);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send(string $to, string $subject, string $template, array $context): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($context);

        $this->mailer->send($email);
    }
}