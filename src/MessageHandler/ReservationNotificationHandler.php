<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Reservation;
use App\Message\ReservationCancelledMessage;
use App\Message\ReservationConfirmedMessage;
use App\Message\ReservationCreatedMessage;
use App\Repository\ReservationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Twig\Environment;

#[AsMessageHandler]
class ReservationNotificationHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,  // ← AJOUTE TWIG
    ) {}

    public function __invoke(ReservationCreatedMessage|ReservationConfirmedMessage|ReservationCancelledMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->getReservationId());
        
        if (!$reservation) {
            return;
        }

        // Charge les relations
        $reservation = $this->reservationRepository->findOneForDetail($reservation);

        match (true) {
            $message instanceof ReservationCreatedMessage => $this->sendPendingEmail($reservation),
            $message instanceof ReservationConfirmedMessage => $this->sendConfirmedEmail($reservation),
            $message instanceof ReservationCancelledMessage => $this->sendCancelledEmail($reservation),
            default => null,
        };
    }

    private function sendPendingEmail(Reservation $reservation): void
    {
        $host = $reservation->getProperty()?->getHost();
        if (!$host) {
            return;
        }

        $html = $this->twig->render('emails/reservation/pending.html.twig', [
            'reservation' => $reservation,
        ]);

        $email = (new Email())
            ->from(new Address($_ENV['MAILER_FROM'] ?? 'noreply@example.com', $_ENV['MAILER_FROM_NAME'] ?? 'Plateforme'))
            ->to($host->getEmail())
            ->subject('Nouvelle demande de réservation')
            ->html($html);

        $this->mailer->send($email);
    }

    private function sendConfirmedEmail(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();

        if ($guest) {
            $html = $this->twig->render('emails/reservation/confirmed_guest.html.twig', [
                'reservation' => $reservation,
            ]);

            $email = (new Email())
                ->from(new Address($_ENV['MAILER_FROM'] ?? 'noreply@example.com', $_ENV['MAILER_FROM_NAME'] ?? 'Plateforme'))
                ->to($guest->getEmail())
                ->subject('Votre réservation est confirmée !')
                ->html($html);
            $this->mailer->send($email);
        }

        if ($host) {
            $html = $this->twig->render('emails/reservation/confirmed_host.html.twig', [
                'reservation' => $reservation,
            ]);

            $email = (new Email())
                ->from(new Address($_ENV['MAILER_FROM'] ?? 'noreply@example.com', $_ENV['MAILER_FROM_NAME'] ?? 'Plateforme'))
                ->to($host->getEmail())
                ->subject(' Réservation confirmée')
                ->html($html);
            $this->mailer->send($email);
        }
    }

    private function sendCancelledEmail(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();

        if ($guest) {
            $html = $this->twig->render('emails/reservation/cancelled_guest.html.twig', [
                'reservation' => $reservation,
            ]);

            $email = (new Email())
                ->from(new Address($_ENV['MAILER_FROM'] ?? 'noreply@example.com', $_ENV['MAILER_FROM_NAME'] ?? 'Plateforme'))
                ->to($guest->getEmail())
                ->subject('Votre réservation a été annulée')
                ->html($html);
            $this->mailer->send($email);
        }

        if ($host) {
            $html = $this->twig->render('emails/reservation/cancelled_host.html.twig', [
                'reservation' => $reservation,
            ]);

            $email = (new Email())
                ->from(new Address($_ENV['MAILER_FROM'] ?? 'noreply@example.com', $_ENV['MAILER_FROM_NAME'] ?? 'Plateforme'))
                ->to($host->getEmail())
                ->subject('Réservation annulée')
                ->html($html);
            $this->mailer->send($email);
        }
    }
}