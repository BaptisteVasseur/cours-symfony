<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Reservation;
use App\Enum\ReservationNotificationType;
use App\Message\SendReservationNotification;
use App\Repository\ReservationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

#[AsMessageHandler]
final class SendReservationNotificationHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(SendReservationNotification $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);
        if ($reservation === null) {
            return;
        }

        $reservation = $this->reservationRepository->findOneForDetail($reservation) ?? $reservation;

        $recipients = $this->resolveRecipients($reservation, $message->type);
        if ($recipients === []) {
            return;
        }

        $subject = $this->resolveSubject($message->type, $reservation);
        $template = $this->resolveTemplate($message->type);

        $html = $this->twig->render($template, [
            'reservation' => $reservation,
            'property' => $reservation->getProperty(),
            'guest' => $reservation->getGuest(),
            'host' => $reservation->getProperty()?->getHost(),
            'manageUrl' => $this->urlGenerator->generate(
                'app_host_reservation_index',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
            'reservationUrl' => $this->urlGenerator->generate(
                'app_reservation_show',
                ['id' => $reservation->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        ]);

        foreach ($recipients as $email) {
            $this->mailer->send(
                (new Email())
                    ->from('noreply@airbnb-clone.local')
                    ->to($email)
                    ->subject($subject)
                    ->html($html),
            );
        }
    }

    /**
     * @return list<string>
     */
    private function resolveRecipients(Reservation $reservation, ReservationNotificationType $type): array
    {
        return match ($type) {
            ReservationNotificationType::PendingRequestToHost,
            ReservationNotificationType::ConfirmedToHost,
            ReservationNotificationType::CancelledToHost => array_filter([
                $reservation->getProperty()?->getHost()?->getEmail(),
            ]),
            ReservationNotificationType::ConfirmedToGuest,
            ReservationNotificationType::RejectedToGuest,
            ReservationNotificationType::CancelledToGuest => array_filter([
                $reservation->getGuest()?->getEmail(),
            ]),
        };
    }

    private function resolveSubject(ReservationNotificationType $type, Reservation $reservation): string
    {
        $title = $reservation->getProperty()?->getTitle() ?? 'Logement';

        return match ($type) {
            ReservationNotificationType::PendingRequestToHost => sprintf('Nouvelle demande de réservation — %s', $title),
            ReservationNotificationType::ConfirmedToGuest => sprintf('Réservation confirmée — %s', $title),
            ReservationNotificationType::ConfirmedToHost => sprintf('Nouvelle réservation confirmée — %s', $title),
            ReservationNotificationType::RejectedToGuest => sprintf('Demande refusée — %s', $title),
            ReservationNotificationType::CancelledToGuest => sprintf('Réservation annulée — %s', $title),
            ReservationNotificationType::CancelledToHost => sprintf('Réservation annulée — %s', $title),
        };
    }

    private function resolveTemplate(ReservationNotificationType $type): string
    {
        return match ($type) {
            ReservationNotificationType::PendingRequestToHost => 'emails/reservation/pending_host.html.twig',
            ReservationNotificationType::ConfirmedToGuest,
            ReservationNotificationType::ConfirmedToHost => 'emails/reservation/confirmed.html.twig',
            ReservationNotificationType::RejectedToGuest => 'emails/reservation/rejected.html.twig',
            ReservationNotificationType::CancelledToGuest,
            ReservationNotificationType::CancelledToHost => 'emails/reservation/cancelled.html.twig',
        };
    }
}
