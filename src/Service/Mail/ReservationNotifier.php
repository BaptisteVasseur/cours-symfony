<?php

declare(strict_types=1);

namespace App\Service\Mail;

use App\Entity\Notification;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Centralise les notifications transactionnelles liées à une réservation
 * (énoncé §Partie D) : un email Twig via Mailpit + une notification in-app
 * persistée en base (icône cloche, §G.8). Appelé par les MessageHandlers,
 * donc exécuté de façon asynchrone par le worker Messenger.
 */
final class ReservationNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $fromEmail = 'no-reply@clone-airbnb.local',
    ) {
    }

    /** Nouvelle demande en attente → hôte. */
    public function requested(Reservation $reservation): void
    {
        $host = $reservation->getProperty()?->getHost();
        if ($host === null) {
            return;
        }

        $this->email($host, 'Nouvelle demande de réservation', 'emails/reservation_requested.html.twig', $reservation);
        $this->inApp(
            $host,
            'reservation_requested',
            'Nouvelle demande de réservation',
            sprintf('Demande pour « %s » du %s au %s.', $this->propertyTitle($reservation), $this->dates($reservation)[0], $this->dates($reservation)[1]),
        );
    }

    /** Réservation confirmée → voyageur + hôte. */
    public function confirmed(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();

        foreach (array_filter([$guest, $host]) as $recipient) {
            $this->email($recipient, 'Votre réservation est confirmée', 'emails/reservation_confirmed.html.twig', $reservation);
            $this->inApp(
                $recipient,
                'reservation_confirmed',
                'Réservation confirmée',
                sprintf('La réservation pour « %s » est confirmée.', $this->propertyTitle($reservation)),
            );
        }
    }

    /** Demande refusée par l'hôte → voyageur. */
    public function rejected(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        if ($guest === null) {
            return;
        }

        $this->email($guest, 'Votre demande de réservation a été refusée', 'emails/reservation_rejected.html.twig', $reservation);
        $this->inApp(
            $guest,
            'reservation_rejected',
            'Demande refusée',
            sprintf('Votre demande pour « %s » a été refusée.', $this->propertyTitle($reservation)),
        );
    }

    /** Réservation annulée → voyageur + hôte. */
    public function cancelled(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();

        foreach (array_filter([$guest, $host]) as $recipient) {
            $this->email($recipient, 'Réservation annulée', 'emails/reservation_cancelled.html.twig', $reservation);
            $this->inApp(
                $recipient,
                'reservation_cancelled',
                'Réservation annulée',
                sprintf('La réservation pour « %s » a été annulée.', $this->propertyTitle($reservation)),
            );
        }
    }

    /** Rappel d'arrivée à J-1 → voyageur (G.2). */
    public function checkinReminder(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        if ($guest === null) {
            return;
        }

        $this->email($guest, 'Votre arrivée approche', 'emails/checkin_reminder.html.twig', $reservation);
        $this->inApp(
            $guest,
            'checkin_reminder',
            'Votre séjour commence demain',
            sprintf('Arrivée à « %s » le %s.', $this->propertyTitle($reservation), $this->dates($reservation)[0]),
        );
    }

    private function email(User $recipient, string $subject, string $template, Reservation $reservation): void
    {
        $email = (new TemplatedEmail())
            ->from($this->fromEmail)
            ->to($recipient->getEmail())
            ->subject($subject)
            ->htmlTemplate($template)
            ->context([
                'reservation' => $reservation,
                'guestName' => $this->displayName($reservation->getGuest()),
                'hostName' => $this->displayName($reservation->getProperty()?->getHost()),
            ]);

        $this->mailer->send($email);
    }

    private function inApp(User $user, string $type, string $title, string $content): void
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setContent($content);
        $notification->setChannel('in_app');

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    private function displayName(?User $user): string
    {
        if ($user === null) {
            return '';
        }

        $profile = $user->getProfile();
        if ($profile !== null && ($profile->getFirstName() || $profile->getLastName())) {
            return trim($profile->getFirstName() . ' ' . $profile->getLastName());
        }

        return (string) $user->getEmail();
    }

    private function propertyTitle(Reservation $reservation): string
    {
        return (string) $reservation->getProperty()?->getTitle();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function dates(Reservation $reservation): array
    {
        return [
            $reservation->getCheckinDate()?->format('d/m/Y') ?? '',
            $reservation->getCheckoutDate()?->format('d/m/Y') ?? '',
        ];
    }
}
