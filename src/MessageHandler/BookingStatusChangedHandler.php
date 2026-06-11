<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Reservation;
use App\Message\BookingStatusChangedMessage;
use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class BookingStatusChangedHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function __invoke(BookingStatusChangedMessage $message): void
    {
        $reservation = $this->reservationRepository->find(Uuid::fromString($message->reservationId));
        if (!$reservation instanceof Reservation) {
            return;
        }

        $guest = $reservation->getGuest();
        $property = $reservation->getProperty();
        $status = $reservation->getStatus();
        $propertyTitle = $property?->getTitle() ?? 'Logement';

        if ($guest === null) {
            return;
        }

        if ($status === 'confirmed') {
            if ($guest->getEmail() !== null) {
                $email = (new TemplatedEmail())
                    ->from('noreply@clone-airbnb.local')
                    ->to($guest->getEmail())
                    ->subject('Votre réservation est confirmée')
                    ->htmlTemplate('email/booking_confirmed_guest.html.twig')
                    ->context([
                        'reservation' => $reservation,
                        'property' => $property,
                    ]);

                $this->mailer->send($email);
            }

            $this->notificationService->notify(
                $guest,
                'booking_confirmed',
                'Réservation confirmée',
                sprintf('Votre séjour à %s est confirmé.', $propertyTitle),
            );

            return;
        }

        if ($status === 'cancelled') {
            if ($guest->getEmail() !== null) {
                $email = (new TemplatedEmail())
                    ->from('noreply@clone-airbnb.local')
                    ->to($guest->getEmail())
                    ->subject('Votre réservation a été annulée')
                    ->htmlTemplate('email/booking_cancelled_guest.html.twig')
                    ->context([
                        'reservation' => $reservation,
                        'property' => $property,
                    ]);

                $this->mailer->send($email);
            }

            $reason = $reservation->getCancellationReason() ?? 'Aucun motif précisé.';
            $this->notificationService->notify(
                $guest,
                'booking_cancelled',
                'Réservation annulée',
                sprintf('Votre réservation pour %s a été annulée. Motif : %s', $propertyTitle, $reason),
            );
        }
    }
}
