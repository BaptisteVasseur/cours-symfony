<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Exception\BookingConflictException;
use App\Exception\UnavailableDatesException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class BookingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AvailabilityService $availabilityService,
        private readonly BookingPriceCalculator $priceCalculator,
    ) {
    }

    public function create(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guestsCount,
    ): Reservation {
        return $this->entityManager->wrapInTransaction(function () use ($property, $guest, $checkin, $checkout, $guestsCount): Reservation {
            $this->assertCreationIsValid($property, $guest, $checkin, $checkout, $guestsCount);

            if (!$this->availabilityService->isAvailable($property, $checkin, $checkout, $guestsCount)) {
                throw new UnavailableDatesException('Ces dates ne sont plus disponibles.');
            }

            $breakdown = $this->priceCalculator->calculateBreakdown($property, $checkin, $checkout);

            $reservation = new Reservation();
            $reservation->setProperty($property);
            $reservation->setGuest($guest);
            $reservation->setCheckinDate($checkin);
            $reservation->setCheckoutDate($checkout);
            $reservation->setGuestsCount($guestsCount);
            $reservation->setStatus('pending');
            $reservation->setTotalPrice($breakdown['totalPrice']);
            $reservation->setCleaningFee($breakdown['cleaningFee'] !== '0.00' ? $breakdown['cleaningFee'] : null);
            $reservation->setServiceFee($breakdown['serviceFee']);
            $reservation->setSecurityDeposit($property->getSecurityDeposit());
            $reservation->setCurrency('EUR');

            $this->entityManager->persist($reservation);
            $this->appendHistory($reservation, null, 'pending', $guest);
            $this->entityManager->flush();

            if ($property->isInstantBooking()) {
                $this->confirmInsideTransaction($reservation, $guest);
            }

            return $reservation;
        });
    }

    public function confirm(Reservation $reservation, ?User $actor): Reservation
    {
        return $this->entityManager->wrapInTransaction(fn (): Reservation => $this->confirmInsideTransaction($reservation, $actor));
    }

    public function refuse(Reservation $reservation, User $actor, string $reason): Reservation
    {
        $this->assertReasonIsProvided($reason, 'Un motif de refus est obligatoire.');

        return $this->entityManager->wrapInTransaction(function () use ($reservation, $actor, $reason): Reservation {
            if ($reservation->getStatus() !== 'pending') {
                throw new \LogicException('Cette réservation ne peut plus être refusée.');
            }

            $reservation->setCancellationReason($reason);
            $this->transitionTo($reservation, 'cancelled', $actor);
            $this->entityManager->flush();

            return $reservation;
        });
    }

    public function cancel(Reservation $reservation, ?User $actor, string $reason): Reservation
    {
        $this->assertReasonIsProvided($reason, 'Un motif d\'annulation est obligatoire.');

        return $this->cancelWithStatusMessage($reservation, $actor, $reason, 'Transition invalide');
    }

    public function expire(Reservation $reservation): Reservation
    {
        return $this->cancel($reservation, null, 'Délai de confirmation expiré (24h)');
    }

    public function markCompleted(Reservation $reservation): Reservation
    {
        return $this->entityManager->wrapInTransaction(function () use ($reservation): Reservation {
            if ($reservation->getStatus() !== 'confirmed') {
                throw new \LogicException('Transition invalide');
            }

            $today = new \DateTimeImmutable('today');
            if ($reservation->getCheckoutDate() === null || $reservation->getCheckoutDate() >= $today) {
                throw new \LogicException('Transition invalide');
            }

            $this->transitionTo($reservation, 'completed', null);
            $this->entityManager->flush();

            return $reservation;
        });
    }

    private function confirmInsideTransaction(Reservation $reservation, ?User $actor): Reservation
    {
        if ($reservation->getStatus() !== 'pending') {
            throw new \LogicException('Transition invalide');
        }

        $property = $reservation->getProperty();
        $checkin = $reservation->getCheckinDate();
        $checkout = $reservation->getCheckoutDate();
        $guestsCount = $reservation->getGuestsCount();

        if ($property === null || $checkin === null || $checkout === null || $guestsCount === null) {
            throw new \LogicException('Réservation invalide');
        }

        $this->entityManager->lock($property, LockMode::PESSIMISTIC_WRITE);

        if (!$this->availabilityService->isAvailable($property, $checkin, $checkout, $guestsCount)) {
            throw new BookingConflictException('Les dates sont déjà prises par une réservation confirmée.');
        }

        $this->transitionTo($reservation, 'confirmed', $actor);
        $this->entityManager->flush();

        return $reservation;
    }

    private function cancelWithStatusMessage(Reservation $reservation, ?User $actor, string $reason, string $invalidMessage): Reservation
    {
        return $this->entityManager->wrapInTransaction(function () use ($reservation, $actor, $reason, $invalidMessage): Reservation {
            if (!in_array($reservation->getStatus(), ['pending', 'confirmed'], true)) {
                throw new \LogicException($invalidMessage);
            }

            $reservation->setCancellationReason($reason);
            $this->transitionTo($reservation, 'cancelled', $actor);
            $this->entityManager->flush();

            return $reservation;
        });
    }

    private function transitionTo(Reservation $reservation, string $newStatus, ?User $actor): void
    {
        $oldStatus = $reservation->getStatus();
        if ($oldStatus === $newStatus) {
            throw new \LogicException('Transition invalide');
        }

        $reservation->setStatus($newStatus);
        $this->appendHistory($reservation, $oldStatus, $newStatus, $actor);
    }

    private function appendHistory(Reservation $reservation, ?string $oldStatus, string $newStatus, ?User $actor): void
    {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus);
        $history->setNewStatus($newStatus);
        $history->setChangedBy($actor);

        $this->entityManager->persist($history);
    }

    private function assertCreationIsValid(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guestsCount,
    ): void {
        if ($property->getHost()?->getId() === $guest->getId()) {
            throw new \LogicException('Vous ne pouvez pas réserver votre propre logement.');
        }

        if ($checkin >= $checkout) {
            throw new \LogicException('La date de départ doit être postérieure à la date d\'arrivée.');
        }

        if ($checkin < new \DateTimeImmutable('today')) {
            throw new \LogicException('La date d\'arrivée doit être future.');
        }

        if ($guestsCount < 1) {
            throw new \LogicException('Il doit y avoir au moins un voyageur.');
        }
    }

    private function assertReasonIsProvided(string $reason, string $message): void
    {
        if (trim($reason) === '') {
            throw new \LogicException($message);
        }
    }
}
