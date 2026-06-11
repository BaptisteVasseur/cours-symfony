<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationCreatedMessage;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ReservationCreatedMessageHandler
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private MailerInterface $mailer,
        private string $senderEmail = 'noreply@staynest.local',
    ) {}

    public function __invoke(ReservationCreatedMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);
        if ($reservation === null) {
            return;
        }

        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();

        if ($guest !== null) {
            $this->mailer->send(
                (new TemplatedEmail())
                    ->from($this->senderEmail)
                    ->to($guest->getEmail())
                    ->subject('Confirmation de votre demande de réservation — ' . $reservation->getProperty()?->getTitle())
                    ->htmlTemplate('emails/reservation_created.html.twig')
                    ->context(['reservation' => $reservation, 'recipient' => $guest, 'role' => 'guest'])
            );
        }

        if ($host !== null) {
            $this->mailer->send(
                (new TemplatedEmail())
                    ->from($this->senderEmail)
                    ->to($host->getEmail())
                    ->subject('Nouvelle demande de réservation — ' . $reservation->getProperty()?->getTitle())
                    ->htmlTemplate('emails/reservation_created.html.twig')
                    ->context(['reservation' => $reservation, 'recipient' => $host, 'role' => 'host'])
            );
        }
    }
}
