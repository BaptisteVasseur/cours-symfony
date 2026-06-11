<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Reservation;
use App\Message\BookingCreatedMessage;
use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class BookingCreatedHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function __invoke(BookingCreatedMessage $message): void
    {
        $reservation = $this->reservationRepository->find(Uuid::fromString($message->reservationId));
        if (!$reservation instanceof Reservation) {
            return;
        }

        $property = $reservation->getProperty();
        $host = $property?->getHost();
        $guest = $reservation->getGuest();
        $status = $reservation->getStatus();
        $propertyTitle = $property?->getTitle() ?? 'Logement';

        if ($status === 'pending' && $host !== null) {
            if ($host->getEmail() !== null) {
                $email = (new TemplatedEmail())
                    ->from('noreply@clone-airbnb.local')
                    ->to($host->getEmail())
                    ->subject('Nouvelle demande de réservation')
                    ->htmlTemplate('email/booking_created_host.html.twig')
                    ->context([
                        'reservation' => $reservation,
                        'property' => $property,
                        'guest' => $guest,
                    ]);

                $this->mailer->send($email);
            }

            $this->notificationService->notify(
                $host,
                'booking_pending',
                'Nouvelle demande de réservation',
                sprintf('Une demande a été faite pour %s.', $propertyTitle),
            );
        }

        if ($status === 'confirmed' && $guest !== null) {
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
        }
    }
}
