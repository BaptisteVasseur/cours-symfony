<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Reservation;
use App\Message\BookingStatusChangedMessage;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class BookingStatusChangedHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(BookingStatusChangedMessage $message): void
    {
        $reservation = $this->reservationRepository->find(Uuid::fromString($message->reservationId));
        if (!$reservation instanceof Reservation) {
            return;
        }

        $guest = $reservation->getGuest();
        $property = $reservation->getProperty();
        $status = $reservation->getStatus();

        if ($guest === null || $guest->getEmail() === null) {
            return;
        }

        if ($status === 'confirmed') {
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

            return;
        }

        if ($status === 'cancelled') {
            $email = (new TemplatedEmail())
                ->from('noreply@clone-airbnb.local')
                ->to($guest->getEmail())
                ->subject('Votre réservation a été annulée')
                ->htmlTemplate('email/booking_cancelled_guest.html.twig')
                ->context([
                    'reservation' => $reservation,
                    'property' => $property,
                ]);

            $this->mailer->send($email);
        }
    }
}
