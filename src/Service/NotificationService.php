<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Reservation;
use App\Entity\User;
use App\Message\ReservationNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Crée les notifications in-app (cloche, Partie G.8) liées aux événements de
 * réservation. Persiste des entités Notification (channel = in_app) pour les
 * destinataires concernés, en miroir des e-mails transactionnels.
 */
final readonly class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function notifyReservation(Reservation $reservation, string $event): void
    {
        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();
        $propertyTitle = $reservation->getProperty()?->getTitle() ?? 'votre logement';
        $guestName = $this->guestName($reservation);

        $hostLink = $this->urlGenerator->generate('app_reservation_requests');
        $showLink = $reservation->getId() !== null
            ? $this->urlGenerator->generate('app_reservation_show', ['id' => (string) $reservation->getId()])
            : null;

        $messages = match ($event) {
            ReservationNotification::EVENT_REQUESTED => [
                [$host, 'Nouvelle demande de réservation', sprintf('%s souhaite réserver « %s ».', $guestName, $propertyTitle), $hostLink],
            ],
            ReservationNotification::EVENT_CONFIRMED => [
                [$guest, 'Réservation confirmée', sprintf('Votre séjour à « %s » est confirmé.', $propertyTitle), $showLink],
                [$host, 'Réservation confirmée', sprintf('La réservation de %s pour « %s » est confirmée.', $guestName, $propertyTitle), $showLink],
            ],
            ReservationNotification::EVENT_REFUSED => [
                [$guest, 'Demande refusée', sprintf('Votre demande pour « %s » a été refusée.', $propertyTitle), $showLink],
            ],
            ReservationNotification::EVENT_CANCELLED => [
                [$guest, 'Réservation annulée', sprintf('La réservation pour « %s » a été annulée.', $propertyTitle), $showLink],
                [$host, 'Réservation annulée', sprintf('La réservation de %s pour « %s » a été annulée.', $guestName, $propertyTitle), $showLink],
            ],
            ReservationNotification::EVENT_CHECKIN_REMINDER => [
                [$guest, 'Votre séjour approche', sprintf('Votre arrivée à « %s » est prévue demain.', $propertyTitle), $showLink],
            ],
            default => [],
        };

        $created = false;
        foreach ($messages as [$recipient, $title, $content, $link]) {
            if (!$recipient instanceof User) {
                continue;
            }
            $this->entityManager->persist($this->build($recipient, $event, $title, $content, $link));
            $created = true;
        }

        if ($created) {
            $this->entityManager->flush();
        }
    }

    private function build(User $user, string $event, string $title, string $content, ?string $link): Notification
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType('reservation_' . $event);
        $notification->setTitle($title);
        $notification->setContent($content);
        $notification->setChannel('in_app');
        $notification->setLinkUrl($link);
        $notification->setIsRead(false);

        return $notification;
    }

    private function guestName(Reservation $reservation): string
    {
        $profile = $reservation->getGuest()?->getProfile();
        $name = trim(sprintf('%s %s', $profile?->getFirstName() ?? '', $profile?->getLastName() ?? ''));

        return $name !== '' ? $name : ($reservation->getGuest()?->getEmail() ?? 'Un voyageur');
    }
}
