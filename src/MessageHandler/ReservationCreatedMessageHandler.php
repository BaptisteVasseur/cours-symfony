<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationCreatedMessage;
use App\Repository\ReservationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

#[AsMessageHandler]
final class ReservationCreatedMessageHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {}

    public function __invoke(ReservationCreatedMessage $message): void
    {
        $reservation = $this->reservationRepository->findOneForDetail(
            $this->reservationRepository->find($message->reservationId),
        );

        if ($reservation === null) {
            return;
        }

        $guest = $reservation->getGuest();
        $property = $reservation->getProperty();
        $host = $property?->getHost();

        if ($guest !== null && $guest->getEmail() !== null) {
            $guestProfile = $guest->getProfile();
            $guestName = $guestProfile?->getFirstName() ?? $guest->getEmail();

            $email = (new TemplatedEmail())
                ->from(new Address('noreply@airbnb-clone.local', 'StayEasy'))
                ->to(new Address($guest->getEmail(), $guestName))
                ->subject('Confirmation de votre réservation')
                ->htmlTemplate('emails/reservation/guest_confirmation.html.twig')
                ->context([
                    'reservation' => $reservation,
                    'property' => $property,
                    'guestName' => $guestName,
                ]);

            $this->mailer->send($email);
        }

        if ($host !== null && $host->getEmail() !== null) {
            $hostProfile = $host->getProfile();
            $hostName = $hostProfile?->getFirstName() ?? $host->getEmail();

            $email = (new TemplatedEmail())
                ->from(new Address('noreply@airbnb-clone.local', 'StayEasy'))
                ->to(new Address($host->getEmail(), $hostName))
                ->subject('Nouvelle demande de réservation')
                ->htmlTemplate('emails/reservation/host_notification.html.twig')
                ->context([
                    'reservation' => $reservation,
                    'property' => $property,
                    'hostName' => $hostName,
                ]);

            $this->mailer->send($email);
        }
    }
}
