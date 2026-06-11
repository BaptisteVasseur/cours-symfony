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
        private readonly string $mailerFrom,
    ) {}

    public function __invoke(ReservationNotificationMessage $message): void
    {
        $reservation = $this->reservationRepository->findOneForDetail(
            $this->reservationRepository->find($message->reservationId)
        );

        if ($reservation === null) {
            return;
        }

        $property = $reservation->getProperty();
        $guest    = $reservation->getGuest();
        $host     = $property?->getHost();

        match ($message->type) {
            'pending' => $this->sendPending($reservation, $host, $guest),
            'confirmed' => $this->sendConfirmed($reservation, $host, $guest),
            'cancelled' => $this->sendCancelled($reservation, $host, $guest, $message->cancellationReason),
            default => null,
        };
    }

    private function sendPending($reservation, $host, $guest): void
    {
        if ($host === null) {
            return;
        }

        $html = $this->twig->render('emails/reservation_pending.html.twig', [
            'reservation' => $reservation,
        ]);

        $this->send($host->getEmail(), 'Nouvelle demande de réservation', $html);
    }

    private function sendConfirmed($reservation, $host, $guest): void
    {
        $html = $this->twig->render('emails/reservation_confirmed.html.twig', [
            'reservation' => $reservation,
        ]);

        foreach (array_filter([$guest, $host]) as $recipient) {
            $this->send($recipient->getEmail(), 'Réservation confirmée', $html);
        }
    }

    private function sendCancelled($reservation, $host, $guest, ?string $reason): void
    {
        $html = $this->twig->render('emails/reservation_cancelled.html.twig', [
            'reservation' => $reservation,
            'reason' => $reason,
        ]);

        foreach (array_filter([$guest, $host]) as $recipient) {
            $this->send($recipient->getEmail(), 'Réservation annulée', $html);
        }
    }

    private function send(string $to, string $subject, string $html): void
    {
        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($to)
            ->subject($subject)
            ->html($html);

        $this->mailer->send($email);
    }
}
