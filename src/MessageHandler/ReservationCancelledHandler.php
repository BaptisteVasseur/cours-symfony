<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationCancelledMessage;
use App\Repository\ReservationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsMessageHandler]
final class ReservationCancelledHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
    ) {
    }

    public function __invoke(ReservationCancelledMessage $message): void
    {
        $found = $this->reservationRepository->find($message->reservationId);
        if ($found === null) {
            return;
        }

        $reservation = $this->reservationRepository->findOneForDetail($found);
        if ($reservation === null) {
            return;
        }

        $html = $this->twig->render('email/reservation_cancelled.html.twig', [
            'reservation' => $reservation,
        ]);

        $recipients = array_filter([
            $reservation->getGuest()?->getEmail(),
            $reservation->getProperty()?->getHost()?->getEmail(),
        ]);

        foreach ($recipients as $to) {
            $email = (new Email())
                ->from('noreply@airbnb-clone.fr')
                ->to($to)
                ->subject('Réservation annulée — ' . $reservation->getProperty()?->getTitle())
                ->html($html);

            $this->mailer->send($email);
        }
    }
}
