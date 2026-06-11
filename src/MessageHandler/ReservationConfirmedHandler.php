<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationConfirmedMessage;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final readonly class ReservationConfirmedHandler
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private MailerInterface $mailer,
    ) {
    }

    public function __invoke(ReservationConfirmedMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->getReservationId());
        if ($reservation === null) {
            return;
        }

        $property = $reservation->getProperty();
        $host = $property?->getHost();
        $guest = $reservation->getGuest();

        $recipients = [];
        if ($guest !== null && $guest->getEmail() !== null) {
            $recipients[] = new Address($guest->getEmail());
        }
        if ($host !== null && $host->getEmail() !== null) {
            $recipients[] = new Address($host->getEmail());
        }

        if ($recipients === []) {
            return;
        }

        $email = (new TemplatedEmail())
            ->to(...$recipients)
            ->subject('Réservation confirmée — ' . ($property?->getTitle() ?? 'Logement'))
            ->htmlTemplate('emails/reservation_confirmed.html.twig')
            ->context([
                'reservation' => $reservation,
                'property' => $property,
                'guest' => $guest,
                'host' => $host,
            ]);

        $this->mailer->send($email);
    }
}
