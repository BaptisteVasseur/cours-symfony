<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Reservation;
use App\Message\NewReservationRequestMessage;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final class NewReservationRequestHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(NewReservationRequestMessage $message): void
    {
        $reservation = $this->entityManager->find(Reservation::class, $message->reservationId);
        if ($reservation === null) {
            return;
        }

        $reservation = $this->reservationRepository->findOneForDetail($reservation) ?? $reservation;

        $host = $reservation->getProperty()?->getHost();
        if ($host === null || $reservation->getProperty() === null) {
            return;
        }

        $hostEmail = $host->getEmail();
        if ($hostEmail === null) {
            return;
        }

        $hostProfile  = $host->getProfile();
        $hostName     = $hostProfile ? trim(($hostProfile->getFirstName() ?? '') . ' ' . ($hostProfile->getLastName() ?? '')) : 'Hôte';
        $hostName     = $hostName ?: 'Hôte';

        $actionUrl = $this->urlGenerator->generate(
            'app_account_properties',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@airbnb-clone.local', 'Réservation'))
            ->to(new Address($hostEmail, $hostName))
            ->subject('Nouvelle demande de réservation — ' . $reservation->getProperty()->getTitle())
            ->htmlTemplate('emails/reservation_new_request.html.twig')
            ->context([
                'reservation' => $reservation,
                'actionUrl'   => $actionUrl,
            ]);

        $this->mailer->send($email);
    }
}
