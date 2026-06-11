<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationCreatedMessage;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ReservationCreatedHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {}

    public function __invoke(ReservationCreatedMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);
        if ($reservation === null) {
            return;
        }

        $guest = $reservation->getGuest();
        $property = $reservation->getProperty();
        $host = $property?->getHost();

        if ($guest === null || $property === null || $host === null) {
            return;
        }

        $guestEmail = (new TemplatedEmail())
            ->to($guest->getEmail() ?? '')
            ->subject('Votre réservation a bien été reçue')
            ->htmlTemplate('emails/reservation_created_guest.html.twig')
            ->context(['reservation' => $reservation]);

        $this->mailer->send($guestEmail);

        $hostEmail = (new TemplatedEmail())
            ->to($host->getEmail() ?? '')
            ->subject('Nouvelle demande de réservation')
            ->htmlTemplate('emails/reservation_created_host.html.twig')
            ->context(['reservation' => $reservation]);

        $this->mailer->send($hostEmail);
    }
}
