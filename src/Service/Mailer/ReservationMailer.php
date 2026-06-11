<?php

declare(strict_types=1);

namespace App\Service\Mailer;

use App\Entity\Reservation;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ReservationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function sendNewRequestToHost(Reservation $reservation): void
    {
        $host = $reservation->getProperty()?->getHost();
        if (!$host instanceof User || $host->getEmail() === null) {
            return;
        }

        $moderationUrl = $this->urlGenerator->generate(
            'app_host_booking_moderate',
            ['id' => (string) $reservation->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = (new TemplatedEmail())
            ->to($host->getEmail())
            ->subject('Nouvelle demande de réservation — '.$reservation->getProperty()?->getTitle())
            ->htmlTemplate('emails/reservation/request_host.html.twig')
            ->context([
                'reservation' => $reservation,
                'guest' => $reservation->getGuest(),
                'property' => $reservation->getProperty(),
                'moderationUrl' => $moderationUrl,
            ]);

        $this->mailer->send($email);
    }

    public function sendBookingConfirmed(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        $property = $reservation->getProperty();
        $host = $property?->getHost();
        $reservationUrl = $this->urlGenerator->generate(
            'app_reservation_show',
            ['id' => (string) $reservation->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        if ($guest instanceof User && $guest->getEmail() !== null) {
            $this->mailer->send(
                (new TemplatedEmail())
                    ->to($guest->getEmail())
                    ->subject('Réservation confirmée — '.$property?->getTitle())
                    ->htmlTemplate('emails/reservation/confirmed_guest.html.twig')
                    ->context([
                        'reservation' => $reservation,
                        'property' => $property,
                        'host' => $host,
                        'reservationUrl' => $reservationUrl,
                    ])
            );
        }

        if ($host instanceof User && $host->getEmail() !== null) {
            $this->mailer->send(
                (new TemplatedEmail())
                    ->to($host->getEmail())
                    ->subject('Réservation confirmée — '.$property?->getTitle())
                    ->htmlTemplate('emails/reservation/confirmed_host.html.twig')
                    ->context([
                        'reservation' => $reservation,
                        'property' => $property,
                        'guest' => $guest,
                        'reservationUrl' => $reservationUrl,
                    ])
            );
        }
    }

    public function sendReservationRefused(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        if (!$guest instanceof User || $guest->getEmail() === null) {
            return;
        }

        $property = $reservation->getProperty();
        $reservationUrl = $this->urlGenerator->generate(
            'app_reservation_show',
            ['id' => (string) $reservation->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = (new TemplatedEmail())
            ->to($guest->getEmail())
            ->subject('Demande de réservation refusée — '.$property?->getTitle())
            ->htmlTemplate('emails/reservation/refused_guest.html.twig')
            ->context([
                'reservation' => $reservation,
                'property' => $property,
                'guest' => $guest,
                'reason' => $reservation->getCancellationReason(),
                'reservationUrl' => $reservationUrl,
            ]);

        $this->mailer->send($email);
    }
}
