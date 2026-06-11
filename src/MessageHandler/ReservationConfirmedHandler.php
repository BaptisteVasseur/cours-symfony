<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Reservation;
use App\Message\ReservationConfirmedMessage;
use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sends confirmation emails to both the guest and the host.
 */
#[AsMessageHandler]
final class ReservationConfirmedHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(ReservationConfirmedMessage $message): void
    {
        $found = $this->reservationRepository->find($message->reservationId);
        if (!$found instanceof Reservation) {
            return;
        }
        $reservation = $this->reservationRepository->findOneForDetail($found) ?? $found;

        $property = $reservation->getProperty();
        $guest = $reservation->getGuest();
        $host = $property?->getHost();

        $guestName = $guest?->getProfile()?->getFirstName() ?? $guest?->getEmail() ?? 'Voyageur';
        $propertyTitle = $property?->getTitle() ?? 'Logement';
        $reservationUrl = $this->urlGenerator->generate('app_reservation_show', ['id' => $reservation->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        // Email to the guest
        if ($guest?->getEmail()) {
            $guestEmail = (new TemplatedEmail())
                ->from(new Address('noreply@clone-airbnb.local', 'Clone Airbnb'))
                ->to(new Address($guest->getEmail()))
                ->subject(sprintf('Réservation confirmée — %s', $propertyTitle))
                ->htmlTemplate('email/reservation_confirmed.html.twig')
                ->context([
                    'reservation' => $reservation,
                    'property' => $property,
                    'recipientName' => $guestName,
                    'isGuest' => true,
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
                ->subject(sprintf('Réservation confirmée — %s', $propertyTitle))
                ->htmlTemplate('email/reservation_confirmed.html.twig')
                ->context([
                    'reservation' => $reservation,
                    'property' => $property,
                    'recipientName' => $hostName,
                    'isGuest' => false,
                    'reservationUrl' => $reservationUrl,
                ]);

            $this->mailer->send($hostEmail);
        }

        // In-app notifications for both parties
        $title = sprintf('Réservation confirmée — %s', $propertyTitle);
        $body = sprintf('Du %s au %s.',
            $reservation->getCheckinDate()?->format('d/m/Y'),
            $reservation->getCheckoutDate()?->format('d/m/Y'),
        );
        if ($guest) {
            $this->notificationService->create($guest, 'reservation_confirmed', $title, $body);
        }
        if ($host) {
            $this->notificationService->create($host, 'reservation_confirmed', $title, $body);
        }
        $this->em->flush();
    }
}
