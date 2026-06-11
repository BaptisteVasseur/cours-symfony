<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationStatusChangedMessage;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ReservationStatusChangedHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {}

    public function __invoke(ReservationStatusChangedMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);
        if ($reservation === null) {
            return;
        }

        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();

        if ($message->newStatus === 'cancelled_by_guest') {
            if ($host === null) {
                return;
            }

            $email = (new TemplatedEmail())
                ->to($host->getEmail() ?? '')
                ->subject('Réservation annulée par le voyageur')
                ->htmlTemplate('emails/reservation_cancelled_by_guest.html.twig')
                ->context(['reservation' => $reservation]);

            $this->mailer->send($email);

            return;
        }

        if ($guest === null) {
            return;
        }

        $template = match ($message->newStatus) {
            'confirmed' => 'emails/reservation_accepted.html.twig',
            'cancelled' => 'emails/reservation_refused.html.twig',
            default => null,
        };

        if ($template === null) {
            return;
        }

        $subject = match ($message->newStatus) {
            'confirmed' => 'Votre réservation a été acceptée',
            'cancelled' => 'Votre réservation a été refusée',
            default => '',
        };

        $email = (new TemplatedEmail())
            ->to($guest->getEmail() ?? '')
            ->subject($subject)
            ->htmlTemplate($template)
            ->context(['reservation' => $reservation]);

        $this->mailer->send($email);
    }
}
