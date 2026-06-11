<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Reservation;
use App\Message\CheckinReminderMessage;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final class CheckinReminderHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {}

    public function __invoke(CheckinReminderMessage $message): void
    {
        $reservation = $this->reservationRepository->findOneForDetail(
            $this->reservationRepository->find($message->reservationId)
        );

        if (!$reservation instanceof Reservation) {
            return;
        }

        $guest = $reservation->getGuest();
        if (!$guest?->getEmail()) {
            return;
        }

        $property = $reservation->getProperty();
        $guestName = $guest->getProfile()?->getFirstName() ?? $guest->getEmail();

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@clone-airbnb.local', 'Clone Airbnb'))
            ->to(new Address($guest->getEmail()))
            ->subject(sprintf('Rappel : votre séjour commence demain — %s', $property?->getTitle() ?? 'Logement'))
            ->htmlTemplate('email/checkin_reminder.html.twig')
            ->context([
                'reservation' => $reservation,
                'property' => $property,
                'guestName' => $guestName,
            ]);

        $this->mailer->send($email);
    }
}
