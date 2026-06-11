<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationCreatedMessage;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final readonly class ReservationCreatedHandler
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private MailerInterface $mailer,
    ) {
    }

    public function __invoke(ReservationCreatedMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->getReservationId());
        if ($reservation === null) {
            return;
        }

        $property = $reservation->getProperty();
        $host = $property?->getHost();
        $guest = $reservation->getGuest();

        if ($host === null || $host->getEmail() === null) {
            return;
        }

        $email = (new TemplatedEmail())
            ->to(new Address($host->getEmail()))
            ->subject('Nouvelle demande de réservation — ' . ($property?->getTitle() ?? 'Logement'))
            ->htmlTemplate('emails/reservation_created.html.twig')
            ->context([
                'reservation' => $reservation,
                'property' => $property,
                'guest' => $guest,
                'host' => $host,
            ]);

        $this->mailer->send($email);
    }
}
