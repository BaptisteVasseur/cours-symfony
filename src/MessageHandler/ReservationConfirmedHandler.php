<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Reservation;
use App\Message\ReservationConfirmedMessage;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final class ReservationConfirmedHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(ReservationConfirmedMessage $message): void
    {
        $reservation = $this->entityManager->find(Reservation::class, $message->reservationId);
        if ($reservation === null) {
            return;
        }

        $reservation = $this->reservationRepository->findOneForDetail($reservation) ?? $reservation;

        $guest    = $reservation->getGuest();
        $property = $reservation->getProperty();
        $host     = $property?->getHost();

        if ($guest === null || $property === null || $host === null) {
            return;
        }

        $guestEmail = $guest->getEmail();
        $hostEmail  = $host->getEmail();

        $guestProfile = $guest->getProfile();
        $hostProfile  = $host->getProfile();

        $guestName = $guestProfile ? trim(($guestProfile->getFirstName() ?? '') . ' ' . ($guestProfile->getLastName() ?? '')) : '';
        $guestName = $guestName ?: ($guestEmail ?? 'Voyageur');
        $hostName  = $hostProfile ? trim(($hostProfile->getFirstName() ?? '') . ' ' . ($hostProfile->getLastName() ?? '')) : '';
        $hostName  = $hostName ?: 'Hôte';

        $reservationUrl = $this->urlGenerator->generate(
            'app_reservation_show',
            ['id' => $reservation->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        if ($guestEmail !== null) {
            $emailToGuest = (new TemplatedEmail())
                ->from(new Address('noreply@airbnb-clone.local', 'Réservation'))
                ->to(new Address($guestEmail, $guestName))
                ->subject('Votre réservation est confirmée — ' . $property->getTitle())
                ->htmlTemplate('emails/reservation_confirmed_guest.html.twig')
                ->context([
                    'reservation'    => $reservation,
                    'reservationUrl' => $reservationUrl,
                    'hostName'       => $hostName,
                ]);

            $this->mailer->send($emailToGuest);
        }

        if ($hostEmail !== null) {
            $emailToHost = (new TemplatedEmail())
                ->from(new Address('noreply@airbnb-clone.local', 'Réservation'))
                ->to(new Address($hostEmail, $hostName))
                ->subject('Réservation confirmée pour ' . $property->getTitle())
                ->htmlTemplate('emails/reservation_confirmed_host.html.twig')
                ->context([
                    'reservation'    => $reservation,
                    'reservationUrl' => $reservationUrl,
                    'guestName'      => $guestName,
                ]);

            $this->mailer->send($emailToHost);
        }
    }
}
