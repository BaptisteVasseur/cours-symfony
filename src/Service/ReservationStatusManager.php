<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ReservationStatusManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReservationMailer $mailer,
    ) {
    }

    public function confirm(Reservation $reservation, User $actor): void
    {
        $this->transition($reservation, 'confirmed', $actor, null);
        $this->mailer->sendDecision($reservation);
    }

    public function refuse(Reservation $reservation, User $actor, string $reason): void
    {
        $this->transition($reservation, 'cancelled', $actor, $reason);
        $this->mailer->sendDecision($reservation);
    }

    private function transition(Reservation $reservation, string $newStatus, User $actor, ?string $reason): void
    {
        $oldStatus = $reservation->getStatus();
        $reservation->setStatus($newStatus);

        if ($reason !== null) {
            $reservation->setCancellationReason($reason);
        }

        $history = (new ReservationStatusHistory())
            ->setReservation($reservation)
            ->setOldStatus($oldStatus)
            ->setNewStatus($newStatus)
            ->setChangedBy($actor)
            ->setCreatedAt(new \DateTimeImmutable());
        $reservation->addStatusHistory($history);

        $this->em->persist($history);
        $this->em->flush();
    }
}