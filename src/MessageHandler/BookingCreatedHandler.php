<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Reservation;
use App\Message\BookingCreatedMessage;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class BookingCreatedHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(BookingCreatedMessage $message): void
    {
        $reservation = $this->reservationRepository->find(Uuid::fromString($message->reservationId));
        if (!$reservation instanceof Reservation) {
            return;
        }

        $property = $reservation->getProperty();
        $host = $property?->getHost();
        if ($host === null || $host->getEmail() === null) {
            return;
        }

        $guest = $reservation->getGuest();
        $status = $reservation->getStatus();

        if ($status === 'pending') {
            $email = (new TemplatedEmail())
                ->from('noreply@clone-airbnb.local')
                ->to($host->getEmail())
                ->subject('Nouvelle demande de réservation')
                ->htmlTemplate('email/booking_created_host.html.twig')
                ->context([
                    'reservation' => $reservation,
                    'property' => $property,
                    'guest' => $guest,
                ]);

            $this->mailer->send($email);
        }

        if ($status === 'confirmed' && $guest !== null && $guest->getEmail() !== null) {
            $email = (new TemplatedEmail())
                ->from('noreply@clone-airbnb.local')
                ->to($guest->getEmail())
                ->subject('Votre réservation est confirmée')
                ->htmlTemplate('email/booking_confirmed_guest.html.twig')
                ->context([
                    'reservation' => $reservation,
                    'property' => $property,
                ]);

            $this->mailer->send($email);
        }
    }
}
