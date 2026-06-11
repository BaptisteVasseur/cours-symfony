<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function createForReservationCreated(Reservation $reservation, User $recipient): void
    {
        $property = $reservation->getProperty();
        
        $notification = new Notification();
        $notification->setUser($recipient);
        $notification->setType('reservation_created');
        $notification->setTitle('Nouvelle demande de réservation');
        $notification->setContent(sprintf(
            'Nouvelle demande pour %s du %s au %s',
            $property->getTitle(),
            $reservation->getCheckinDate()->format('d/m/Y'),
            $reservation->getCheckoutDate()->format('d/m/Y')
        ));
        $notification->setChannel('in_app');
        $notification->setIsRead(false);

        $this->em->persist($notification);
        $this->em->flush();
    }

    public function createForReservationConfirmed(Reservation $reservation, User $recipient): void
    {
        $property = $reservation->getProperty();
        $isHost = $property->getHost() === $recipient;

        $notification = new Notification();
        $notification->setUser($recipient);
        $notification->setType('reservation_confirmed');
        $notification->setTitle($isHost ? 'Réservation confirmée' : 'Votre réservation est confirmée');
        $notification->setContent(sprintf(
            'Réservation confirmée pour %s du %s au %s',
            $property->getTitle(),
            $reservation->getCheckinDate()->format('d/m/Y'),
            $reservation->getCheckoutDate()->format('d/m/Y')
        ));
        $notification->setChannel('in_app');
        $notification->setIsRead(false);

        $this->em->persist($notification);
        $this->em->flush();
    }

    public function createForReservationCancelled(Reservation $reservation, User $recipient): void
    {
        $property = $reservation->getProperty();

        $notification = new Notification();
        $notification->setUser($recipient);
        $notification->setType('reservation_cancelled');
        $notification->setTitle('Réservation annulée');
        $notification->setContent(sprintf(
            'Réservation annulée pour %s du %s au %s',
            $property->getTitle(),
            $reservation->getCheckinDate()->format('d/m/Y'),
            $reservation->getCheckoutDate()->format('d/m/Y')
        ));
        $notification->setChannel('in_app');
        $notification->setIsRead(false);

        $this->em->persist($notification);
        $this->em->flush();
    }
}