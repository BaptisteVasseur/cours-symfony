<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Reservation;
use App\Message\ReservationCancelledMessage;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final class ReservationCancelledHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(ReservationCancelledMessage $message): void
    {
        $reservation = $this->entityManager->find(Reservation::class, $message->reservationId);
        if ($reservation === null) {
            return;
        }

        $reservation = $this->reservationRepository->findOneForDetail($reservation) ?? $reservation;

        $guest    = $reservation->getGuest();
        $property = $reservation->getProperty();

        if ($guest === null || $property === null) {
            return;
        }

        $guestEmail = $guest->getEmail();
        if ($guestEmail === null) {
            return;
        }

        $guestProfile = $guest->getProfile();
        $guestName    = $guestProfile ? trim(($guestProfile->getFirstName() ?? '') . ' ' . ($guestProfile->getLastName() ?? '')) : '';
        $guestName    = $guestName ?: $guestEmail;

        $homeUrl = $this->urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@airbnb-clone.local', 'Réservation'))
            ->to(new Address($guestEmail, $guestName))
            ->subject('Votre demande de réservation a été refusée — ' . $property->getTitle())
            ->htmlTemplate('emails/reservation_cancelled.html.twig')
            ->context([
                'reservation' => $reservation,
                'homeUrl'     => $homeUrl,
            ]);

        $this->mailer->send($email);
    }
}
