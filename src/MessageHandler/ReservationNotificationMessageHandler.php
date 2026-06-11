<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationNotificationMessage;
use App\Repository\ReservationRepository;
use App\Service\ReservationWorkflowService;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReservationNotificationMessageHandler
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private MailerInterface $mailer,
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
        $host = $property?->getHost();
        $guest = $reservation->getGuest();

        if ($property === null || $host === null || $guest === null) {
            return;
        }

        $subject = $this->subjectForEvent($message->getEvent(), $property->getTitle() ?? 'Réservation');
        $recipients = match ($message->getEvent()) {
            ReservationWorkflowService::EVENT_PENDING_CREATED => [$host->getEmail()],
            default => array_filter([$host->getEmail(), $guest->getEmail()]),
        };

        foreach ($recipients as $recipient) {
            if ($recipient === null || $recipient === '') {
                continue;
            }

            $this->mailer->send(
                (new TemplatedEmail())
                    ->from('no-reply@clone-airbnb.local')
                    ->to($recipient)
                    ->subject($subject)
                    ->htmlTemplate('email/reservation_notification.html.twig')
                    ->textTemplate('email/reservation_notification.txt.twig')
                    ->context([
                        'event' => $message->getEvent(),
                        'subject' => $subject,
                        'reservation' => $reservation,
                        'property' => $property,
                        'host' => $host,
                        'guest' => $guest,
                        'isRecipientHost' => $recipient === $host->getEmail(),
                    ]),
            );
        }
    }

    private function subjectForEvent(string $event, string $propertyTitle): string
    {
        return match ($event) {
            ReservationWorkflowService::EVENT_PENDING_CREATED => sprintf('Nouvelle demande de réservation - %s', $propertyTitle),
            ReservationWorkflowService::EVENT_CONFIRMED_CREATED => sprintf('Réservation confirmée - %s', $propertyTitle),
            ReservationWorkflowService::EVENT_CONFIRMED => sprintf('Demande acceptée - %s', $propertyTitle),
            ReservationWorkflowService::EVENT_REFUSED => sprintf('Demande refusée - %s', $propertyTitle),
            ReservationWorkflowService::EVENT_CANCELLED => sprintf('Réservation annulée - %s', $propertyTitle),
            default => sprintf('Mise à jour de réservation - %s', $propertyTitle),
        };
    }
}
