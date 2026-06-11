<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Reservation;
use App\Message\ReservationPendingMessage;
use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sends an email to the property host when a new pending reservation is created.
 */
#[AsMessageHandler]
final class ReservationPendingHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(ReservationPendingMessage $message): void
    {
        $found = $this->reservationRepository->find($message->reservationId);
        if (!$found instanceof Reservation) {
            return;
        }
        $reservation = $this->reservationRepository->findOneForDetail($found) ?? $found;

        $property = $reservation->getProperty();
        $host = $property?->getHost();
        if (!$host?->getEmail()) {
            return;
        }

        $guest = $reservation->getGuest();
        $guestName = $guest?->getProfile()?->getFirstName() ?? $guest?->getEmail() ?? 'Un voyageur';

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@clone-airbnb.local', 'Clone Airbnb'))
            ->to(new Address($host->getEmail()))
            ->subject(sprintf('Nouvelle demande de réservation — %s', $property?->getTitle() ?? 'Logement'))
            ->htmlTemplate('email/reservation_pending.html.twig')
            ->context([
                'reservation' => $reservation,
                'property' => $property,
                'guestName' => $guestName,
                'dashboardUrl' => $this->urlGenerator->generate('app_host_reservations_pending', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);

        $this->mailer->send($email);

        // In-app notification for the host
        if ($host) {
            $this->notificationService->create(
                $host,
                'reservation_pending',
                sprintf('Nouvelle demande — %s', $property?->getTitle() ?? 'Logement'),
                sprintf('%s souhaite réserver du %s au %s.', $guestName,
                    $reservation->getCheckinDate()?->format('d/m/Y'),
                    $reservation->getCheckoutDate()?->format('d/m/Y'),
                ),
            );
            $this->em->flush();
        }
    }
}
