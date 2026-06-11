<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationStatusChangedMessage;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final class ReservationStatusChangedMessageHandler
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private string $senderEmail = 'noreply@staynest.local',
    ) {}

    public function __invoke(ReservationStatusChangedMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);
        if ($reservation === null) {
            return;
        }

        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();
        $subject = match ($message->newStatus) {
            'confirmed' => 'Votre réservation a été confirmée — ' . $reservation->getProperty()?->getTitle(),
            'cancelled' => 'Votre réservation a été annulée — ' . $reservation->getProperty()?->getTitle(),
            default => 'Mise à jour de votre réservation — ' . $reservation->getProperty()?->getTitle(),
        };

        $reservationUrl = $this->urlGenerator->generate(
            'app_reservation_show',
            ['id' => $reservation->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        foreach ([$guest, $host] as $recipient) {
            if ($recipient === null) {
                continue;
            }

            $this->mailer->send(
                (new TemplatedEmail())
                    ->from($this->senderEmail)
                    ->to($recipient->getEmail())
                    ->subject($subject)
                    ->htmlTemplate('emails/reservation_status_changed.html.twig')
                    ->context([
                        'reservation' => $reservation,
                        'recipient' => $recipient,
                        'newStatus' => $message->newStatus,
                        'reservationUrl' => $reservationUrl,
                    ])
            );
        }
    }
}
