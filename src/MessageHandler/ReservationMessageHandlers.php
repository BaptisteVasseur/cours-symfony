<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationConfirmedMessage;
use App\Message\ReservationRejectedMessage;
use App\Message\ReservationCancelledMessage;
use App\Message\ReservationCreatedMessage;
use App\Repository\ReservationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ReservationConfirmedMessageHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(ReservationConfirmedMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->getReservationId());
        if ($reservation === null) {
            return;
        }

        $guest = $reservation->getGuest();
        $property = $reservation->getProperty();
        $host = $property->getHost();

        // Email to guest
        $guestEmail = new Email();
        $guestEmail
            ->from('noreply@airbnb-clone.local')
            ->to($guest->getEmail())
            ->subject('Réservation confirmée')
            ->html(sprintf(
                '<h1>Votre réservation est confirmée</h1>
                <p>Propriété: %s</p>
                <p>Du %s au %s</p>
                <p>Total: %s€</p>',
                $property->getTitle(),
                $reservation->getCheckinDate()->format('d/m/Y'),
                $reservation->getCheckoutDate()->format('d/m/Y'),
                $reservation->getTotalPrice()
            ));

        $this->mailer->send($guestEmail);

        // Email to host
        $hostEmail = new Email();
        $hostEmail
            ->from('noreply@airbnb-clone.local')
            ->to($host->getEmail())
            ->subject('Nouvelle réservation confirmée')
            ->html(sprintf(
                '<h1>Réservation confirmée</h1>
                <p>Propriété: %s</p>
                <p>Voyageur: %s (%s)</p>
                <p>Du %s au %s</p>
                <p>Total: %s€</p>',
                $property->getTitle(),
                $guest->getUserIdentifier(),
                $guest->getEmail(),
                $reservation->getCheckinDate()->format('d/m/Y'),
                $reservation->getCheckoutDate()->format('d/m/Y'),
                $reservation->getTotalPrice()
            ));

        $this->mailer->send($hostEmail);
    }
}

#[AsMessageHandler]
final class ReservationRejectedMessageHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(ReservationRejectedMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->getReservationId());
        if ($reservation === null) {
            return;
        }

        $guest = $reservation->getGuest();
        $property = $reservation->getProperty();

        $email = new Email();
        $email
            ->from('noreply@airbnb-clone.local')
            ->to($guest->getEmail())
            ->subject('Votre demande de réservation a été refusée')
            ->html(sprintf(
                '<h1>Demande de réservation refusée</h1>
                <p>Propriété: %s</p>
                <p>Motif: %s</p>
                <p>Nous vous encourageons à rechercher d\'autres propriétés.</p>',
                $property->getTitle(),
                htmlspecialchars($message->getReason())
            ));

        $this->mailer->send($email);
    }
}

#[AsMessageHandler]
final class ReservationCancelledMessageHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(ReservationCancelledMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->getReservationId());
        if ($reservation === null) {
            return;
        }

        $guest = $reservation->getGuest();
        $property = $reservation->getProperty();
        $host = $property->getHost();

        // Email to guest
        $guestEmail = new Email();
        $guestEmail
            ->from('noreply@airbnb-clone.local')
            ->to($guest->getEmail())
            ->subject('Annulation de réservation')
            ->html(sprintf(
                '<h1>Votre réservation a été annulée</h1>
                <p>Propriété: %s</p>
                <p>Motif: %s</p>',
                $property->getTitle(),
                htmlspecialchars($message->getReason())
            ));

        $this->mailer->send($guestEmail);

        // Email to host
        $hostEmail = new Email();
        $hostEmail
            ->from('noreply@airbnb-clone.local')
            ->to($host->getEmail())
            ->subject('Annulation de réservation')
            ->html(sprintf(
                '<h1>Réservation annulée</h1>
                <p>Propriété: %s</p>
                <p>Voyageur: %s</p>
                <p>Motif: %s</p>',
                $property->getTitle(),
                $guest->getUserIdentifier(),
                htmlspecialchars($message->getReason())
            ));

        $this->mailer->send($hostEmail);
    }
}

#[AsMessageHandler]
final class ReservationCreatedMessageHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(ReservationCreatedMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->getReservationId());
        if ($reservation === null) {
            return;
        }

        $guest = $reservation->getGuest();
        $property = $reservation->getProperty();
        $host = $property->getHost();

        if ($message->isPending()) {
            // Send email to host for approval
            $email = new Email();
            $email
                ->from('noreply@airbnb-clone.local')
                ->to($host->getEmail())
                ->subject('Nouvelle demande de réservation')
                ->html(sprintf(
                    '<h1>Nouvelle demande de réservation</h1>
                    <p>Propriété: %s</p>
                    <p>Voyageur: %s (%s)</p>
                    <p>Du %s au %s (%d nuits)</p>
                    <p>Total: %s€</p>
                    <p>Merci de valider ou refuser cette demande.</p>',
                    $property->getTitle(),
                    $guest->getUserIdentifier(),
                    $guest->getEmail(),
                    $reservation->getCheckinDate()->format('d/m/Y'),
                    $reservation->getCheckoutDate()->format('d/m/Y'),
                    $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days,
                    $reservation->getTotalPrice()
                ));

            $this->mailer->send($email);
        } else {
            // Instant booking - send confirmation to both
            $guestEmail = new Email();
            $guestEmail
                ->from('noreply@airbnb-clone.local')
                ->to($guest->getEmail())
                ->subject('Réservation confirmée')
                ->html(sprintf(
                    '<h1>Votre réservation est confirmée</h1>
                    <p>Propriété: %s</p>
                    <p>Du %s au %s</p>
                    <p>Total: %s€</p>',
                    $property->getTitle(),
                    $reservation->getCheckinDate()->format('d/m/Y'),
                    $reservation->getCheckoutDate()->format('d/m/Y'),
                    $reservation->getTotalPrice()
                ));

            $this->mailer->send($guestEmail);
        }
    }
}
