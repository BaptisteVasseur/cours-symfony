<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingCancelledMessage;
use App\Message\BookingConfirmedMessage;
use App\Message\BookingRequestedMessage;
use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsMessageHandler]
final class BookingEmailHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly NotificationService $notificationService,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $mailerFrom,
    ) {}

    public function __invoke(BookingRequestedMessage|BookingConfirmedMessage|BookingCancelledMessage $message): void
    {
        $reservation = $this->reservationRepository->findOneForDetail(
            $this->reservationRepository->find($message->reservationId)
        );

        if ($reservation === null) {
            return;
        }

        match (true) {
            $message instanceof BookingRequestedMessage => $this->sendBookingRequested($reservation),
            $message instanceof BookingConfirmedMessage => $this->sendBookingConfirmed($reservation),
            $message instanceof BookingCancelledMessage => $this->sendBookingCancelled($reservation, $message->reason, $message->cancelledBy),
        };
    }

    private function sendBookingRequested(\App\Entity\Reservation $reservation): void
    {
        $host = $reservation->getProperty()?->getHost();
        if ($host === null) {
            return;
        }

        $html = $this->twig->render('emails/booking_requested.html.twig', [
            'reservation' => $reservation,
        ]);

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($host->getEmail())
            ->subject('Nouvelle demande de réservation')
            ->html($html);

        $this->mailer->send($email);

        $this->notificationService->notify(
            $host,
            'Nouvelle demande de réservation',
            sprintf('Demande de %s pour "%s"', $reservation->getGuest()?->getEmail(), $reservation->getProperty()?->getTitle()),
            'booking_requested',
        );
    }

    private function sendBookingConfirmed(\App\Entity\Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();

        $html = $this->twig->render('emails/booking_confirmed.html.twig', [
            'reservation' => $reservation,
        ]);

        if ($guest !== null) {
            $this->mailer->send(
                (new Email())
                    ->from($this->mailerFrom)
                    ->to($guest->getEmail())
                    ->subject('Votre réservation est confirmée !')
                    ->html($html)
            );
            $this->notificationService->notify(
                $guest,
                'Réservation confirmée !',
                sprintf('Votre séjour à "%s" est confirmé.', $reservation->getProperty()?->getTitle()),
                'booking_confirmed',
            );
        }

        if ($host !== null) {
            $this->mailer->send(
                (new Email())
                    ->from($this->mailerFrom)
                    ->to($host->getEmail())
                    ->subject('Réservation confirmée pour votre logement')
                    ->html($html)
            );
            $this->notificationService->notify(
                $host,
                'Réservation confirmée',
                sprintf('La réservation pour "%s" est confirmée.', $reservation->getProperty()?->getTitle()),
                'booking_confirmed',
            );
        }
    }

    private function sendBookingCancelled(\App\Entity\Reservation $reservation, string $reason, string $cancelledBy): void
    {
        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();

        $html = $this->twig->render('emails/booking_cancelled.html.twig', [
            'reservation' => $reservation,
            'reason' => $reason,
            'cancelledBy' => $cancelledBy,
        ]);

        if ($guest !== null) {
            $this->mailer->send(
                (new Email())
                    ->from($this->mailerFrom)
                    ->to($guest->getEmail())
                    ->subject('Réservation annulée')
                    ->html($html)
            );
            $this->notificationService->notify(
                $guest,
                'Réservation annulée',
                sprintf('Votre séjour à "%s" a été annulé. Motif : %s', $reservation->getProperty()?->getTitle(), $reason),
                'booking_cancelled',
            );
        }

        if ($host !== null) {
            $this->mailer->send(
                (new Email())
                    ->from($this->mailerFrom)
                    ->to($host->getEmail())
                    ->subject('Réservation annulée')
                    ->html($html)
            );
            $this->notificationService->notify(
                $host,
                'Réservation annulée',
                sprintf('La réservation pour "%s" a été annulée. Motif : %s', $reservation->getProperty()?->getTitle(), $reason),
                'booking_cancelled',
            );
        }
    }
}
