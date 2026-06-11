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
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function notifyBookingPending(Reservation $reservation): void
    {
        $host = $reservation->getProperty()?->getHost();
        if ($host === null) {
            return;
        }

        $this->create(
            $host,
            'booking_pending',
            'Nouvelle demande de réservation',
            \sprintf(
                '%s souhaite réserver "%s" du %s au %s.',
                $this->guestName($reservation),
                $reservation->getProperty()?->getTitle() ?? '',
                $reservation->getCheckinDate()->format('d/m/Y'),
                $reservation->getCheckoutDate()->format('d/m/Y'),
            ),
        );
    }

    public function notifyBookingConfirmed(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        if ($guest !== null) {
            $this->create(
                $guest,
                'booking_confirmed',
                'Réservation confirmée',
                \sprintf(
                    'Votre réservation pour "%s" du %s au %s a été confirmée.',
                    $reservation->getProperty()?->getTitle() ?? '',
                    $reservation->getCheckinDate()->format('d/m/Y'),
                    $reservation->getCheckoutDate()->format('d/m/Y'),
                ),
            );
        }

        $host = $reservation->getProperty()?->getHost();
        if ($host !== null) {
            $this->create(
                $host,
                'booking_confirmed',
                'Réservation confirmée',
                \sprintf(
                    'La réservation de %s pour "%s" est confirmée.',
                    $this->guestName($reservation),
                    $reservation->getProperty()?->getTitle() ?? '',
                ),
            );
        }
    }

    public function notifyBookingCancelled(Reservation $reservation, User $cancelledBy, string $reason): void
    {
        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();

        $message = \sprintf(
            'La réservation pour "%s" du %s au %s a été annulée. Motif : %s',
            $reservation->getProperty()?->getTitle() ?? '',
            $reservation->getCheckinDate()->format('d/m/Y'),
            $reservation->getCheckoutDate()->format('d/m/Y'),
            $reason,
        );

        if ($guest !== null && $guest->getId() !== $cancelledBy->getId()) {
            $this->create($guest, 'booking_cancelled', 'Réservation annulée', $message);
        }

        if ($host !== null && $host->getId() !== $cancelledBy->getId()) {
            $this->create($host, 'booking_cancelled', 'Réservation annulée', $message);
        }
    }

    public function countUnread(User $user): int
    {
        return (int) $this->entityManager->getRepository(Notification::class)
            ->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function create(User $user, string $type, string $title, string $content): void
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setContent($content);
        $notification->setChannel('database');
        $notification->setIsRead(false);
        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    private function guestName(Reservation $reservation): string
    {
        $guest = $reservation->getGuest();
        if ($guest === null) {
            return 'Un voyageur';
        }
        $profile = $guest->getProfile();

        return $profile
            ? trim(($profile->getFirstName() ?? '').' '.($profile->getLastName() ?? '')) ?: $guest->getEmail()
            : $guest->getEmail();
    }
}
