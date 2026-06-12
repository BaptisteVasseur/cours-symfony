<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\User;
use App\Enum\ReservationNotificationType;
use App\Message\ReservationNotification;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class ReservationNotificationHandler
{
    private const FROM = 'no-reply@airbnb-clone.fr';

    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(ReservationNotification $message): void
    {
        $reservation = $this->reservationRepository->find(Uuid::fromString($message->reservationId));
        if ($reservation === null) {
            return;
        }

        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();

        foreach ($this->recipients($message->type, $guest, $host) as [$recipient, $audience]) {
            if (!$recipient instanceof User || $recipient->getEmail() === null) {
                continue;
            }

            $subject = $this->subject($message->type, $audience);

            $email = (new TemplatedEmail())
                ->from(self::FROM)
                ->to($recipient->getEmail())
                ->subject($subject)
                ->htmlTemplate('emails/reservation/notification.html.twig')
                ->context([
                    'reservation' => $reservation,
                    'type' => $message->type->value,
                    'audience' => $audience,
                    'subject' => $subject,
                ]);

            $this->mailer->send($email);
        }
    }

    /**
     * @return list<array{0: ?User, 1: string}>
     */
    private function recipients(ReservationNotificationType $type, ?User $guest, ?User $host): array
    {
        return match ($type) {
            ReservationNotificationType::CREATED_PENDING,
            ReservationNotificationType::CREATED_CONFIRMED,
            ReservationNotificationType::CANCELLED => [[$guest, 'guest'], [$host, 'host']],
            ReservationNotificationType::ACCEPTED,
            ReservationNotificationType::REFUSED => [[$guest, 'guest']],
        };
    }

    private function subject(ReservationNotificationType $type, string $audience): string
    {
        return match ($type) {
            ReservationNotificationType::CREATED_PENDING => $audience === 'guest'
                ? 'Demande de réservation envoyée'
                : 'Nouvelle demande de réservation',
            ReservationNotificationType::CREATED_CONFIRMED => $audience === 'guest'
                ? 'Votre réservation est confirmée'
                : 'Nouvelle réservation confirmée',
            ReservationNotificationType::ACCEPTED => 'Votre demande a été acceptée',
            ReservationNotificationType::REFUSED => 'Votre demande a été refusée',
            ReservationNotificationType::CANCELLED => 'Réservation annulée',
        };
    }
}
