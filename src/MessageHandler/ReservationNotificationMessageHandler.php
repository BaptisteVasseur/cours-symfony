<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Reservation;
use App\Message\ReservationNotificationMessage;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final class ReservationNotificationMessageHandler
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(ReservationNotificationMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->getReservationId());
        if ($reservation === null) {
            return;
        }

        $reservation = $this->reservationRepository->findOneForDetail($reservation) ?? $reservation;
        $property = $reservation->getProperty();
        $guest = $reservation->getGuest();
        $host = $property?->getHost();

        if ($property === null || $guest === null || $host === null) {
            return;
        }

        foreach ($this->resolveRecipients($message, $reservation) as $recipient) {
            $email = $this->buildEmail($message, $reservation, $recipient['role'], $recipient['email']);
            if ($email !== null) {
                $this->mailer->send($email);
            }
        }
    }

    /**
     * @return list<array{email: string, role: string}>
     */
    private function resolveRecipients(ReservationNotificationMessage $message, Reservation $reservation): array
    {
        $property = $reservation->getProperty();
        $guestEmail = $reservation->getGuest()?->getEmail();
        $hostEmail = $property?->getHost()?->getEmail();

        if ($guestEmail === null || $hostEmail === null) {
            return [];
        }

        return match ($message->getType()) {
            ReservationNotificationMessage::TYPE_REQUEST_CREATED => [
                ['email' => $hostEmail, 'role' => 'host'],
            ],
            ReservationNotificationMessage::TYPE_CONFIRMED,
            ReservationNotificationMessage::TYPE_REJECTED,
            ReservationNotificationMessage::TYPE_CANCELLED => [
                ['email' => $guestEmail, 'role' => 'guest'],
                ['email' => $hostEmail, 'role' => 'host'],
            ],
            default => [],
        };
    }

    private function buildEmail(
        ReservationNotificationMessage $message,
        Reservation $reservation,
        string $recipientRole,
        string $recipientEmail,
    ): ?TemplatedEmail {
        $property = $reservation->getProperty();
        $guest = $reservation->getGuest();
        $host = $property?->getHost();

        if ($property === null || $guest === null || $host === null) {
            return null;
        }

        $reservationUrl = $this->urlGenerator->generate('app_reservation_show', [
            'id' => $reservation->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $context = $this->buildContext($message, $reservation, $recipientRole, $reservationUrl);

        return (new TemplatedEmail())
            ->from(new Address('no-reply@airbnb.local', 'Airbnb Clone'))
            ->to($recipientEmail)
            ->subject($context['subject'])
            ->htmlTemplate('emails/reservation_notification.html.twig')
            ->context($context);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(
        ReservationNotificationMessage $message,
        Reservation $reservation,
        string $recipientRole,
        string $reservationUrl,
    ): array {
        $property = $reservation->getProperty();
        $guest = $reservation->getGuest();
        $host = $property?->getHost();

        $counterpartLabel = $recipientRole === 'host' ? 'Voyageur' : 'Hote';
        $counterpartEmail = $recipientRole === 'host' ? $guest?->getEmail() : $host?->getEmail();

        $subject = 'Notification reservation';
        $title = 'Mise a jour de reservation';
        $intro = 'Une mise a jour est disponible pour votre reservation.';
        $actionLabel = 'Voir la reservation';

        if ($message->getType() === ReservationNotificationMessage::TYPE_REQUEST_CREATED) {
            $subject = sprintf('Nouvelle demande de reservation - %s', $property?->getTitle() ?? 'Logement');
            $title = 'Nouvelle demande de reservation';
            $intro = 'Une nouvelle demande de reservation attend votre validation.';
            $actionLabel = 'Traiter la demande';
        } elseif ($message->getType() === ReservationNotificationMessage::TYPE_CONFIRMED) {
            $subject = sprintf('Reservation confirmee - %s', $property?->getTitle() ?? 'Logement');
            $title = 'Reservation confirmee';
            $intro = 'La reservation vient d etre confirmee.';
        } elseif ($message->getType() === ReservationNotificationMessage::TYPE_REJECTED) {
            $subject = sprintf('Demande de reservation refusee - %s', $property?->getTitle() ?? 'Logement');
            $title = 'Demande refusee';
            $intro = 'La demande de reservation a ete refusee.';
        } elseif ($message->getType() === ReservationNotificationMessage::TYPE_CANCELLED) {
            $subject = sprintf('Reservation annulee - %s', $property?->getTitle() ?? 'Logement');
            $title = 'Reservation annulee';
            $intro = 'La reservation a ete annulee.';
        }

        return [
            'subject' => $subject,
            'title' => $title,
            'intro' => $intro,
            'reservation' => $reservation,
            'property' => $property,
            'guest' => $guest,
            'host' => $host,
            'counterpartLabel' => $counterpartLabel,
            'counterpartEmail' => $counterpartEmail,
            'actionUrl' => $reservationUrl,
            'actionLabel' => $actionLabel,
            'reason' => $reservation->getCancellationReason(),
            'initiatorLabel' => $this->resolveInitiatorLabel($message->getInitiator()),
        ];
    }

    private function resolveInitiatorLabel(?string $initiator): ?string
    {
        return match ($initiator) {
            ReservationNotificationMessage::INITIATOR_GUEST => 'Annulation a l initiative du voyageur.',
            ReservationNotificationMessage::INITIATOR_HOST => 'Annulation a l initiative de l hote.',
            ReservationNotificationMessage::INITIATOR_SYSTEM => 'Annulation automatique du systeme.',
            default => null,
        };
    }
}
