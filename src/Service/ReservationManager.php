<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ReservationManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AvailabilityChecker $availabilityChecker,
        private readonly PricingCalculator $pricingCalculator,
        private readonly ReservationMailer $mailer,
        private readonly ReservationRepository $reservations,
    ) {
    }

    public function completeEndedStays(\DateTimeImmutable $asOf): int
    {
        $ended = $this->reservations->findConfirmedEndedBefore($asOf);

        foreach ($ended as $reservation) {
            $this->transition($reservation, 'completed', null);
        }

        if ($ended !== []) {
            $this->em->flush();
        }

        return \count($ended);
    }

    public function requestBooking(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
    ): Reservation {
        $reservation = $this->em->wrapInTransaction(function () use ($property, $guest, $checkin, $checkout, $guests): Reservation {
            $reason = $this->availabilityChecker->getUnavailabilityReason($property, $checkin, $checkout, $guests);
            if ($reason !== null) {
                throw new \DomainException($reason);
            }

            $pricing = $this->pricingCalculator->calculate($property, $checkin, $checkout);

            $reservation = new Reservation();
            $reservation->setProperty($property);
            $reservation->setGuest($guest);
            $reservation->setCheckinDate($checkin);
            $reservation->setCheckoutDate($checkout);
            $reservation->setGuestsCount($guests);
            $reservation->setStatus($property->isInstantBooking() ? 'confirmed' : 'pending');
            $reservation->setTotalPrice((string) $pricing['total']);
            $reservation->setCleaningFee($pricing['cleaningFee'] > 0 ? (string) $pricing['cleaningFee'] : null);
            $reservation->setServiceFee((string) $pricing['serviceFee']);
            $reservation->setSecurityDeposit($property->getSecurityDeposit());
            $reservation->setCurrency('EUR');

            $this->em->persist($reservation);
            $this->addHistory($reservation, null, (string) $reservation->getStatus(), $guest);

            return $reservation;
        });

        if ($reservation->getStatus() === 'confirmed') {
            $this->mailer->notifyConfirmed($reservation);
        } else {
            $this->mailer->notifyNewRequest($reservation);
        }

        return $reservation;
    }

    public function confirm(Reservation $reservation, User $actor): void
    {
        if ($reservation->getStatus() !== 'pending') {
            throw new \DomainException('Seule une demande en attente peut être acceptée.');
        }

        $this->em->wrapInTransaction(function () use ($reservation, $actor): void {
            $reason = $this->availabilityChecker->getUnavailabilityReason(
                $reservation->getProperty(),
                $reservation->getCheckinDate(),
                $reservation->getCheckoutDate(),
                (int) $reservation->getGuestsCount(),
                $reservation,
            );
            if ($reason !== null) {
                throw new \DomainException($reason);
            }

            $this->transition($reservation, 'confirmed', $actor);
        });

        $this->mailer->notifyConfirmed($reservation);
    }

    public function reject(Reservation $reservation, User $actor, string $reason): void
    {
        if ($reservation->getStatus() !== 'pending') {
            throw new \DomainException('Seule une demande en attente peut être refusée.');
        }

        $reservation->setCancellationReason($reason);
        $this->transition($reservation, 'cancelled', $actor);
        $this->em->flush();

        $this->mailer->notifyRejected($reservation);
    }

    public function cancel(Reservation $reservation, User $actor, string $reason): void
    {
        if (in_array($reservation->getStatus(), ['cancelled', 'completed'], true)) {
            throw new \DomainException('Cette réservation ne peut plus être annulée.');
        }

        $reservation->setCancellationReason($reason);
        $this->transition($reservation, 'cancelled', $actor);
        $this->em->flush();

        $this->mailer->notifyCancelled($reservation);
    }

    private function transition(Reservation $reservation, string $newStatus, ?User $actor): void
    {
        $oldStatus = $reservation->getStatus();
        $reservation->setStatus($newStatus);
        $this->addHistory($reservation, $oldStatus, $newStatus, $actor);
    }

    private function addHistory(Reservation $reservation, ?string $oldStatus, string $newStatus, ?User $actor): void
    {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus);
        $history->setNewStatus($newStatus);
        $history->setChangedBy($actor);

        $this->em->persist($history);
        $reservation->addStatusHistory($history);
    }
}
