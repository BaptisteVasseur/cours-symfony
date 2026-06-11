<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationPendingMessage;
use App\Repository\ReservationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final class ReservationPendingMessageHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {}

    public function __invoke(ReservationPendingMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);
        if (!$reservation) {
            return;
        }

        $property = $reservation->getProperty();
        $host     = $property->getHost();
        $guest    = $reservation->getGuest();

        if ($host === null) {
            return;
        }

        $email = (new Email())
            ->from('noreply@airbnb-clone.local')
            ->to($host->getEmail())
            ->subject('Nouvelle demande de réservation — ' . $property->getTitle())
            ->html(sprintf(
                '<h2>Nouvelle demande de réservation</h2>
                <p><strong>Logement :</strong> %s</p>
                <p><strong>Voyageur :</strong> %s %s (%s)</p>
                <p><strong>Arrivée :</strong> %s</p>
                <p><strong>Départ :</strong> %s</p>
                <p><strong>Voyageurs :</strong> %d</p>
                <p><strong>Total :</strong> %s €</p>
                <p>Connectez-vous pour accepter ou refuser cette demande.</p>',
                htmlspecialchars($property->getTitle()),
                htmlspecialchars($guest->getFirstName() ?? ''),
                htmlspecialchars($guest->getLastName() ?? ''),
                htmlspecialchars($guest->getEmail()),
                $reservation->getCheckinDate()->format('d/m/Y'),
                $reservation->getCheckoutDate()->format('d/m/Y'),
                $reservation->getGuestsCount(),
                $reservation->getTotalPrice(),
            ));

        $this->mailer->send($email);
    }
}
