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
final class ReservationCancelledMessageHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {}

    public function __invoke(ReservationCancelledMessage $message): void
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
        $cancelledByRole = $message->cancelledByRole;
        $reason = $message->reason;

        // Email au voyageur
        if ($guest !== null && $guest->getEmail() !== null) {
            $guestProfile = $guest->getProfile();
            $guestName = $guestProfile?->getFirstName() ?? $guest->getEmail();

            $this->mailer->send(
                (new TemplatedEmail())
                    ->from(new Address('noreply@airbnb-clone.local', 'StayEasy'))
                    ->to(new Address($guest->getEmail(), $guestName))
                    ->subject('Annulation de votre réservation')
                    ->htmlTemplate('emails/reservation/cancellation_guest.html.twig')
                    ->context([
                        'reservation' => $reservation,
                        'property' => $property,
                        'guestName' => $guestName,
                        'cancelledByRole' => $cancelledByRole,
                        'reason' => $reason,
                    ])
            );
        }

        // Email à l'hôte
        if ($host !== null && $host->getEmail() !== null) {
            $hostProfile = $host->getProfile();
            $hostName = $hostProfile?->getFirstName() ?? $host->getEmail();

            $this->mailer->send(
                (new TemplatedEmail())
                    ->from(new Address('noreply@airbnb-clone.local', 'StayEasy'))
                    ->to(new Address($host->getEmail(), $hostName))
                    ->subject('Annulation d\'une réservation')
                    ->htmlTemplate('emails/reservation/cancellation_host.html.twig')
                    ->context([
                        'reservation' => $reservation,
                        'property' => $property,
                        'hostName' => $hostName,
                        'cancelledByRole' => $cancelledByRole,
                        'reason' => $reason,
                    ])
            );
        }
    }
}
