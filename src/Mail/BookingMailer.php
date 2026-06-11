<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Reservation;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class BookingMailer
{
    private const FROM = 'noreply@stayhub.local';
    private const FROM_NAME = 'StayHub';

    public function __construct(
        private readonly MailerInterface $mailer,
    ) {}

    public function sendBookingRequest(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        $property = $reservation->getProperty();
        $host = $property?->getHost();

        if ($guest === null || $host === null || $property === null) {
            return;
        }

        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address(self::FROM, self::FROM_NAME))
                ->to(new Address((string) $guest->getEmail()))
                ->subject('Demande de réservation envoyée — '.$property->getTitle())
                ->htmlTemplate('email/booking_request.html.twig')
                ->context(['reservation' => $reservation, 'recipient' => 'guest'])
        );

        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address(self::FROM, self::FROM_NAME))
                ->to(new Address((string) $host->getEmail()))
                ->subject('Nouvelle demande de réservation — '.$property->getTitle())
                ->htmlTemplate('email/booking_request.html.twig')
                ->context(['reservation' => $reservation, 'recipient' => 'host'])
        );
    }

    public function sendBookingConfirmed(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        $property = $reservation->getProperty();

        if ($guest === null || $property === null) {
            return;
        }

        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address(self::FROM, self::FROM_NAME))
                ->to(new Address((string) $guest->getEmail()))
                ->subject('Réservation confirmée — '.$property->getTitle())
                ->htmlTemplate('email/booking_confirmed.html.twig')
                ->context(['reservation' => $reservation])
        );
    }

    public function sendBookingCancelled(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        $property = $reservation->getProperty();
        $host = $property?->getHost();

        if ($guest === null || $property === null) {
            return;
        }

        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address(self::FROM, self::FROM_NAME))
                ->to(new Address((string) $guest->getEmail()))
                ->subject('Réservation annulée — '.$property->getTitle())
                ->htmlTemplate('email/booking_cancelled.html.twig')
                ->context(['reservation' => $reservation, 'recipient' => 'guest'])
        );

        if ($host !== null) {
            $this->mailer->send(
                (new TemplatedEmail())
                    ->from(new Address(self::FROM, self::FROM_NAME))
                    ->to(new Address((string) $host->getEmail()))
                    ->subject('Réservation annulée — '.$property->getTitle())
                    ->htmlTemplate('email/booking_cancelled.html.twig')
                    ->context(['reservation' => $reservation, 'recipient' => 'host'])
            );
        }
    }
}
