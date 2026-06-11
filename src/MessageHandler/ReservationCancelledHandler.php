<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationCancelledMessage;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final class ReservationCancelledHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {}

    public function __invoke(ReservationCancelledMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);
        if ($reservation === null) {
            return;
        }

        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();

        if ($guest !== null && $guest->getEmail() !== null) {
            $guestName = $guest->getProfile()?->getFirstName() ?? $guest->getEmail();
            $this->mailer->send(
                (new TemplatedEmail())
                    ->to(new Address($guest->getEmail(), $guestName))
                    ->subject('Annulation de votre réservation - ' . $reservation->getProperty()?->getTitle())
                    ->htmlTemplate('emails/reservation_cancelled.html.twig')
                    ->context(['reservation' => $reservation, 'recipient' => 'guest'])
            );
        }

        if ($host !== null && $host->getEmail() !== null) {
            $hostName = $host->getProfile()?->getFirstName() ?? $host->getEmail();
            $this->mailer->send(
                (new TemplatedEmail())
                    ->to(new Address($host->getEmail(), $hostName))
                    ->subject('Annulation d\'une réservation - ' . $reservation->getProperty()?->getTitle())
                    ->htmlTemplate('emails/reservation_cancelled.html.twig')
                    ->context(['reservation' => $reservation, 'recipient' => 'host'])
            );
        }
    }
}