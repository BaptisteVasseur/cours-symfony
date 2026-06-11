<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationNotification;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

/**
 * Consomme les ReservationNotification et envoie les e-mails correspondants
 * vers le serveur SMTP (Mailpit en local). Chaque destinataire reçoit un
 * message dédié, conformément au tableau des notifications (Partie D).
 */
#[AsMessageHandler]
final readonly class ReservationNotificationHandler
{
    private const FROM_ADDRESS = 'no-reply@clone-airbnb.local';
    private const FROM_NAME = 'Clone Airbnb';

    public function __construct(
        private ReservationRepository $reservationRepository,
        private MailerInterface $mailer,
    ) {
    }

    public function __invoke(ReservationNotification $notification): void
    {
        $reservation = $this->reservationRepository->find($notification->reservationId);
        if ($reservation === null) {
            return;
        }

        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();

        $recipients = match ($notification->event) {
            ReservationNotification::EVENT_REQUESTED => [$host],
            ReservationNotification::EVENT_CONFIRMED => [$guest, $host],
            ReservationNotification::EVENT_REFUSED => [$guest],
            ReservationNotification::EVENT_CANCELLED => [$guest, $host],
            default => [],
        };

        $subject = match ($notification->event) {
            ReservationNotification::EVENT_REQUESTED => 'Nouvelle demande de réservation',
            ReservationNotification::EVENT_CONFIRMED => 'Votre réservation est confirmée',
            ReservationNotification::EVENT_REFUSED => 'Votre demande de réservation a été refusée',
            ReservationNotification::EVENT_CANCELLED => 'Réservation annulée',
            default => 'Mise à jour de votre réservation',
        };

        foreach ($recipients as $recipient) {
            $email = $recipient?->getEmail();
            if ($email === null) {
                continue;
            }

            $message = (new TemplatedEmail())
                ->from(new Address(self::FROM_ADDRESS, self::FROM_NAME))
                ->to($email)
                ->subject($subject)
                ->htmlTemplate('emails/reservation_notification.html.twig')
                ->context([
                    'event' => $notification->event,
                    'reservation' => $reservation,
                    'recipientIsHost' => $host?->getId()?->equals($recipient->getId()) === true,
                ]);

            $this->mailer->send($message);
        }
    }
}
