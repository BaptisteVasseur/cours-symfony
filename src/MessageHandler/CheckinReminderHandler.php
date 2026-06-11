<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\CheckinReminderMessage;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class CheckinReminderHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {}

    public function __invoke(CheckinReminderMessage $message): void
    {
        $reservation = $this->reservationRepository->find(Uuid::fromString($message->reservationId));
        if ($reservation === null) {
            return;
        }

        $guest = $reservation->getGuest();
        if ($guest === null) {
            return;
        }

        $this->mailer->send(
            (new TemplatedEmail())
                ->to($guest->getEmail())
                ->subject('Votre séjour commence demain — '.$reservation->getProperty()?->getTitle())
                ->htmlTemplate('emails/checkin_reminder.html.twig')
                ->context(['reservation' => $reservation])
        );
    }
}
