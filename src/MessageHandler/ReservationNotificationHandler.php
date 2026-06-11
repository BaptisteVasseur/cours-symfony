<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Reservation;
use App\Message\ReservationNotification;
use App\Repository\ReservationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Uid\Uuid;

/**
 * Construit et envoie les emails transactionnels d'une réservation (Partie D).
 * Exécuté de façon asynchrone par le worker Messenger.
 */
#[AsMessageHandler]
final readonly class ReservationNotificationHandler
{
    private const FROM = 'no-reply@airstay.local';

    public function __construct(
        private ReservationRepository $reservationRepository,
        private MailerInterface $mailer,
    ) {
    }

    public function __invoke(ReservationNotification $notification): void
    {
        $reservation = $this->reservationRepository->find(Uuid::fromString($notification->reservationId));
        if ($reservation === null) {
            return;
        }

        [$subject, $template, $recipients] = $this->resolve($notification->event, $reservation);
        if ($recipients === []) {
            return;
        }

        foreach ($recipients as $recipient) {
            $email = (new TemplatedEmail())
                ->from(new Address(self::FROM, 'Airstay'))
                ->to($recipient)
                ->subject($subject)
                ->htmlTemplate('emails/' . $template . '.html.twig')
                ->context(['reservation' => $reservation]);

            $this->mailer->send($email);
        }
    }

    /**
     * @return array{0:string, 1:string, 2:list<string>}
     */
    private function resolve(string $event, Reservation $reservation): array
    {
        $guest = $reservation->getGuest()?->getEmail();
        $host = $reservation->getProperty()?->getHost()?->getEmail();

        return match ($event) {
            ReservationNotification::EVENT_NEW_REQUEST => [
                'Nouvelle demande de réservation',
                'reservation_new_request',
                array_filter([$host]),
            ],
            ReservationNotification::EVENT_CONFIRMED => [
                'Réservation confirmée',
                'reservation_confirmed',
                array_filter([$guest, $host]),
            ],
            ReservationNotification::EVENT_REFUSED => [
                'Votre demande a été refusée',
                'reservation_refused',
                array_filter([$guest]),
            ],
            ReservationNotification::EVENT_CANCELLED => [
                'Réservation annulée',
                'reservation_cancelled',
                array_filter([$guest, $host]),
            ],
            default => ['', '', []],
        };
    }
}
