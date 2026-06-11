<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Reservation;
use App\Enum\ReservationNotificationType;
use App\Message\ReservationNotification;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class ReservationNotificationHandler
{
    private const FROM = 'no-reply@clone-airbnb.local';

    public function __construct(
        private readonly ReservationRepository $reservations,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(ReservationNotification $message): void
    {
        $reservation = $this->reservations->find(Uuid::fromString($message->reservationId));
        if ($reservation === null) {
            return;
        }

        $recipients = $this->resolveRecipients($reservation, $message->type);
        if ($recipients === []) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address(self::FROM, 'Clone Airbnb'))
            ->to(...$recipients)
            ->subject($message->type->subject())
            ->htmlTemplate($message->type->template())
            ->context([
                'reservation' => $reservation,
                'type' => $message->type,
            ]);

        $this->mailer->send($email);
    }

    /**
     * @return list<Address>
     */
    private function resolveRecipients(Reservation $reservation, ReservationNotificationType $type): array
    {
        $emails = [
            'guest' => $reservation->getGuest()?->getEmail(),
            'host' => $reservation->getProperty()?->getHost()?->getEmail(),
        ];

        $addresses = [];
        foreach ($type->recipients() as $role) {
            if ($emails[$role] !== null) {
                $addresses[] = new Address($emails[$role]);
            }
        }

        return $addresses;
    }
}
