<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Reservation;
use App\Message\ReservationCancelledMessage;
use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sends cancellation emails to both the guest and the host.
 */
#[AsMessageHandler]
final class ReservationCancelledHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(ReservationCancelledMessage $message): void
    {
        $found = $this->reservationRepository->find($message->reservationId);
        if (!$found instanceof Reservation) {
            return;
        }
        $reservation = $this->reservationRepository->findOneForDetail($found) ?? $found;

        $property = $reservation->getProperty();
        $guest = $reservation->getGuest();
        $host = $property?->getHost();
        $propertyTitle = $property?->getTitle() ?? 'Logement';
        $reservationUrl = $this->urlGenerator->generate('app_reservation_show', ['id' => $reservation->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        // Email to the guest
        if ($guest?->getEmail()) {
            $guestName = $guest->getProfile()?->getFirstName() ?? $guest->getEmail() ?? 'Voyageur';
            $guestEmail = (new TemplatedEmail())
                ->from(new Address('noreply@clone-airbnb.local', 'Clone Airbnb'))
                ->to(new Address($guest->getEmail()))
                ->subject(sprintf('Réservation annulée — %s', $propertyTitle))
                ->htmlTemplate('email/reservation_cancelled.html.twig')
                ->context([
                    'reservation' => $reservation,
                    'property' => $property,
                    'recipientName' => $guestName,
                    'cancellationReason' => $message->reason,
                    'reservationUrl' => $reservationUrl,
                ]);

            $this->mailer->send($guestEmail);
        }

        // Email to the host
        if ($host?->getEmail()) {
            $hostName = $host->getProfile()?->getFirstName() ?? $host->getEmail() ?? 'Hôte';
            $hostEmail = (new TemplatedEmail())
                ->from(new Address('noreply@clone-airbnb.local', 'Clone Airbnb'))
                ->to(new Address($host->getEmail()))
                ->subject(sprintf('Réservation annulée — %s', $propertyTitle))
                ->htmlTemplate('email/reservation_cancelled.html.twig')
                ->context([
                    'reservation' => $reservation,
                    'property' => $property,
                    'recipientName' => $hostName,
                    'cancellationReason' => $message->reason,
                    'reservationUrl' => $reservationUrl,
                ]);

            $this->mailer->send($hostEmail);
        }

        // In-app notifications for both parties
        $title = sprintf('Réservation annulée — %s', $propertyTitle);
        if ($guest) {
            $this->notificationService->create($guest, 'reservation_cancelled', $title, $message->reason);
        }
        if ($host) {
            $this->notificationService->create($host, 'reservation_cancelled', $title, $message->reason);
        }
        $this->em->flush();
    }
}
