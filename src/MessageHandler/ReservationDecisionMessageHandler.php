<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationDecisionMessage;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final class ReservationDecisionMessageHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {}

    public function __invoke(ReservationDecisionMessage $message): void
    {
        $reservation = $this->reservationRepository->findOneForDetail(
            $this->reservationRepository->find($message->reservationId),
        );

        if ($reservation === null) {
            return;
        }

        $guest = $reservation->getGuest();
        $property = $reservation->getProperty();

        if ($guest === null || $guest->getEmail() === null) {
            return;
        }

        $guestProfile = $guest->getProfile();
        $guestName = $guestProfile?->getFirstName() ?? $guest->getEmail();

        $isAccepted = $message->decision === 'accepted';

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@airbnb-clone.local', 'StayEasy'))
            ->to(new Address($guest->getEmail(), $guestName))
            ->subject($isAccepted ? 'Votre réservation a été acceptée !' : 'Votre demande de réservation a été refusée')
            ->htmlTemplate($isAccepted
                ? 'emails/reservation/guest_accepted.html.twig'
                : 'emails/reservation/guest_rejected.html.twig'
            )
            ->context([
                'reservation' => $reservation,
                'property' => $property,
                'guestName' => $guestName,
                'reason' => $message->reason,
            ]);

        $this->mailer->send($email);
    }
}
