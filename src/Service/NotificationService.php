<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function notifyBookingRequest(Reservation $reservation): void
    {
        $host = $reservation->getProperty()?->getHost();
        if ($host === null) {
            return;
        }

        $this->create(
            $host,
            'booking_request',
            'Nouvelle demande de réservation',
            'Un voyageur souhaite réserver "'.$reservation->getProperty()?->getTitle().'".'
        );

        $guest = $reservation->getGuest();
        if ($guest !== null) {
            $this->create(
                $guest,
                'booking_request',
                'Demande envoyée',
                'Votre demande pour "'.$reservation->getProperty()?->getTitle().'" a bien été envoyée.'
            );
        }
    }

    public function notifyBookingConfirmed(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        if ($guest === null) {
            return;
        }

        $this->create(
            $guest,
            'booking_confirmed',
            'Réservation confirmée',
            'Votre réservation pour "'.$reservation->getProperty()?->getTitle().'" est confirmée.'
        );
    }

    public function notifyBookingCancelled(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        if ($guest !== null) {
            $this->create(
                $guest,
                'booking_cancelled',
                'Réservation annulée',
                'Votre réservation pour "'.$reservation->getProperty()?->getTitle().'" a été annulée.'
            );
        }

        $host = $reservation->getProperty()?->getHost();
        if ($host !== null) {
            $this->create(
                $host,
                'booking_cancelled',
                'Réservation annulée',
                'Une réservation pour "'.$reservation->getProperty()?->getTitle().'" a été annulée.'
            );
        }
    }

    private function create(User $user, string $type, string $title, string $content): void
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setContent($content);
        $notification->setChannel('in_app');
        $notification->setIsRead(false);

        $this->em->persist($notification);
    }
}
