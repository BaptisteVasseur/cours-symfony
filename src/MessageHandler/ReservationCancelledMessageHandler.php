<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationCancelledMessage;
use App\Repository\ReservationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final class ReservationCancelledMessageHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {}

    public function __invoke(ReservationCancelledMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);
        if (!$reservation) {
            return;
        }

        $host     = $reservation->getProperty()->getHost();
        $guest    = $reservation->getGuest();
        $property = $reservation->getProperty();

        $body = sprintf(
            '<h2>Réservation annulée</h2>
            <p><strong>Logement :</strong> %s</p>
            <p><strong>Arrivée :</strong> %s</p>
            <p><strong>Départ :</strong> %s</p>
            <p><strong>Annulée par :</strong> %s</p>
            <p><strong>Motif :</strong> %s</p>',
            htmlspecialchars($property->getTitle()),
            $reservation->getCheckinDate()->format('d/m/Y'),
            $reservation->getCheckoutDate()->format('d/m/Y'),
            htmlspecialchars($message->cancelledBy),
            htmlspecialchars($message->reason),
        );

        foreach ([$guest->getEmail(), $host->getEmail()] as $recipient) {
            $email = (new Email())
                ->from('noreply@airbnb-clone.local')
                ->to($recipient)
                ->subject('Réservation annulée — ' . $property->getTitle())
                ->html($body);

            $this->mailer->send($email);
        }
    }
}
