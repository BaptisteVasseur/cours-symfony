<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationCreatedMessage;
use App\Repository\ReservationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsMessageHandler]
final class ReservationCreatedHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
    ) {
    }

    public function __invoke(ReservationCreatedMessage $message): void
    {
        $found = $this->reservationRepository->find($message->reservationId);
        if ($found === null) {
            return;
        }

        $reservation = $this->reservationRepository->findOneForDetail($found);
        if ($reservation === null) {
            return;
        }

        $hostEmail = $reservation->getProperty()?->getHost()?->getEmail();
        if ($hostEmail === null) {
            return;
        }

        $html = $this->twig->render('email/reservation_created.html.twig', [
            'reservation' => $reservation,
        ]);

        $email = (new Email())
            ->from('noreply@airbnb-clone.fr')
            ->to($hostEmail)
            ->subject('Nouvelle demande de réservation — ' . $reservation->getProperty()?->getTitle())
            ->html($html);

        $this->mailer->send($email);
    }
}
