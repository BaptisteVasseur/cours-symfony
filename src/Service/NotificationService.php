<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationService
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function notifyReservationCreated(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();
        $title = $reservation->getProperty()?->getTitle();
        $checkin = $reservation->getCheckinDate()?->format('d/m/Y');
        $checkout = $reservation->getCheckoutDate()?->format('d/m/Y');

        if ($host !== null) {
            $guestName = $guest?->getProfile()?->getFirstName() ?? $guest?->getEmail();
            $this->persist($host, 'booking_request', 'Nouvelle demande de réservation',
                sprintf('%s souhaite réserver "%s" du %s au %s.', $guestName, $title, $checkin, $checkout));
        }

        if ($guest !== null) {
            $isInstant = $reservation->getStatus() === 'confirmed';
            $this->persist($guest,
                $isInstant ? 'reservation_confirmed' : 'booking_request',
                $isInstant ? 'Réservation confirmée' : 'Demande envoyée',
                sprintf('Votre %s pour "%s" du %s au %s a bien été enregistrée.',
                    $isInstant ? 'réservation' : 'demande', $title, $checkin, $checkout));
        }
    }

    public function notifyStatusChanged(Reservation $reservation, string $newStatus): void
    {
        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();
        $title = $reservation->getProperty()?->getTitle();
        $checkin = $reservation->getCheckinDate()?->format('d/m/Y');
        $checkout = $reservation->getCheckoutDate()?->format('d/m/Y');

        $type = match ($newStatus) {
            'confirmed' => 'reservation_confirmed',
            'cancelled' => 'reservation_cancelled',
            default => 'reservation_updated',
        };
        $notifTitle = match ($newStatus) {
            'confirmed' => 'Réservation confirmée',
            'cancelled' => 'Réservation annulée',
            default => 'Mise à jour de réservation',
        };
        $label = $newStatus === 'confirmed' ? 'confirmée' : 'annulée';

        foreach ([$guest, $host] as $recipient) {
            if ($recipient === null) {
                continue;
            }
            $this->persist($recipient, $type, $notifTitle,
                sprintf('Votre réservation pour "%s" du %s au %s est %s.', $title, $checkin, $checkout, $label));
        }
    }

    private function persist(User $user, string $type, string $title, string $content): void
    {
        $notif = new Notification();
        $notif->setUser($user);
        $notif->setType($type);
        $notif->setTitle($title);
        $notif->setContent($content);
        $notif->setChannel('in_app');
        $notif->setIsRead(false);
        $notif->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($notif);
    }
}
