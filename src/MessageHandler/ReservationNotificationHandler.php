<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationNotificationMessage;
use App\Repository\ReservationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsMessageHandler]
final class ReservationNotificationHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
    ) {}

    public function __invoke(ReservationNotificationMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);
        if ($reservation === null) {
            return;
        }

        $property = $reservation->getProperty();
        $guest = $reservation->getGuest();
        $host = $property?->getHost();

        match ($message->type) {
            ReservationNotificationMessage::TYPE_NEW => $this->sendNewReservationEmail($reservation),
            ReservationNotificationMessage::TYPE_CONFIRMED => $this->sendConfirmedEmail($reservation),
            ReservationNotificationMessage::TYPE_CANCELLED => $this->sendCancelledEmail($reservation),
            default => null,
        };
    }

    private function sendNewReservationEmail(object $reservation): void
    {
        $host = $reservation->getProperty()?->getHost();
        if ($host === null) {
            return;
        }

        $html = $this->twig->render('email/reservation_new.html.twig', [
            'reservation' => $reservation,
        ]);

        $email = (new Email())
            ->to($host->getEmail())
            ->subject('Nouvelle demande de réservation')
            ->html($html);

        $this->mailer->send($email);
    }

    private function sendConfirmedEmail(object $reservation): void
    {
        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();

        $html = $this->twig->render('email/reservation_confirmed.html.twig', [
            'reservation' => $reservation,
        ]);

        if ($guest !== null) {
            $this->mailer->send(
                (new Email())->to($guest->getEmail())->subject('Votre réservation est confirmée')->html($html)
            );
        }

        if ($host !== null) {
            $this->mailer->send(
                (new Email())->to($host->getEmail())->subject('Réservation confirmée')->html($html)
            );
        }
    }

    private function sendCancelledEmail(object $reservation): void
    {
        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();

        $html = $this->twig->render('email/reservation_cancelled.html.twig', [
            'reservation' => $reservation,
        ]);

        if ($guest !== null) {
            $this->mailer->send(
                (new Email())->to($guest->getEmail())->subject('Réservation annulée')->html($html)
            );
        }

        if ($host !== null) {
            $this->mailer->send(
                (new Email())->to($host->getEmail())->subject('Réservation annulée')->html($html)
            );
        }
    }
}
