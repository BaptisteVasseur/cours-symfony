<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ReservationMailer
{
    private const FROM_EMAIL = 'no-reply@clone-airbnb.local';
    private const FROM_NAME = 'Clone Airbnb';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function notifyNewRequest(Reservation $reservation): void
    {
        $host = $reservation->getProperty()?->getHost();
        if ($host?->getEmail() === null) {
            return;
        }

        $this->send(
            $host->getEmail(),
            'Nouvelle demande de réservation',
            'emails/reservation_new_request.html.twig',
            $reservation,
            ['actionUrl' => $this->url('app_host_dashboard')],
        );
    }

    public function notifyConfirmed(Reservation $reservation): void
    {
        $detailUrl = $this->url('app_reservation_show', ['id' => $reservation->getId()]);

        $guest = $reservation->getGuest();
        if ($guest?->getEmail() !== null) {
            $this->send($guest->getEmail(), 'Votre réservation est confirmée', 'emails/reservation_confirmed.html.twig', $reservation, [
                'recipientRole' => 'guest',
                'detailUrl' => $detailUrl,
            ]);
        }

        $host = $reservation->getProperty()?->getHost();
        if ($host?->getEmail() !== null) {
            $this->send($host->getEmail(), 'Une réservation a été confirmée', 'emails/reservation_confirmed.html.twig', $reservation, [
                'recipientRole' => 'host',
                'detailUrl' => $detailUrl,
            ]);
        }
    }

    public function notifyRejected(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        if ($guest?->getEmail() === null) {
            return;
        }

        $this->send($guest->getEmail(), 'Votre demande de réservation a été refusée', 'emails/reservation_rejected.html.twig', $reservation, []);
    }

    public function notifyCancelled(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        if ($guest?->getEmail() !== null) {
            $this->send($guest->getEmail(), 'Réservation annulée', 'emails/reservation_cancelled.html.twig', $reservation, ['recipientRole' => 'guest']);
        }

        $host = $reservation->getProperty()?->getHost();
        if ($host?->getEmail() !== null) {
            $this->send($host->getEmail(), 'Réservation annulée', 'emails/reservation_cancelled.html.twig', $reservation, ['recipientRole' => 'host']);
        }
    }

    private function send(string $to, string $subject, string $template, Reservation $reservation, array $context): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($context + ['reservation' => $reservation]);

        $this->mailer->send($email);
    }

    private function url(string $route, array $parameters = []): string
    {
        return $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
