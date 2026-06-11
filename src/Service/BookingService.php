<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Enum\BookingStatus;
use App\Exception\BookingConflictException;
use App\Exception\UnavailableDatesException;
use App\Message\BookingCancelledMessage;
use App\Message\BookingConfirmedMessage;
use App\Message\BookingCreatedMessage;
use App\Message\BookingRefusedMessage;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class BookingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AvailabilityService $availabilityService,
        private readonly BookingPriceCalculator $priceCalculator,
        private readonly MessageBusInterface $messageBus,
        private readonly RealtimePublisher $realtimePublisher,
    ) {
    }

    public function create(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guestsCount,
    ): Reservation {
        $reservation = $this->entityManager->wrapInTransaction(function () use ($property, $guest, $checkin, $checkout, $guestsCount): Reservation {
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
            $reservation->setBookingStatus(BookingStatus::PENDING);
            $reservation->setTotalPrice($breakdown['totalPrice']);
            $reservation->setCleaningFee($breakdown['cleaningFee'] !== '0.00' ? $breakdown['cleaningFee'] : null);
            $reservation->setServiceFee($breakdown['serviceFee']);
            $reservation->setSecurityDeposit($property->getSecurityDeposit());
            $reservation->setCurrency('EUR');
            $reservation->setHost($property->getHost());
            $reservation->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($reservation);
            $this->appendHistory($reservation, null, BookingStatus::PENDING, $guest);
            $this->entityManager->flush();

            if ($property->isInstantBooking()) {
                $this->confirmInsideTransaction($reservation, $guest);
            }

            return $reservation;
        });

        if ($reservation->getBookingStatus() === BookingStatus::CONFIRMED) {
            $this->messageBus->dispatch(new BookingConfirmedMessage($this->reservationId($reservation)));
            $this->realtimePublisher->publishReservationChanged($reservation, 'reservation.confirmed');
            $this->realtimePublisher->publishAvailabilityChanged($reservation);
        } else {
            $this->messageBus->dispatch(new BookingCreatedMessage($this->reservationId($reservation)));
            $this->realtimePublisher->publishReservationChanged($reservation, 'reservation.created');
        }

        return $reservation;
    }

    public function confirm(Reservation $reservation, ?User $actor): Reservation
    {
        $reservation = $this->entityManager->wrapInTransaction(fn (): Reservation => $this->confirmInsideTransaction($reservation, $actor));

        $this->messageBus->dispatch(new BookingConfirmedMessage($this->reservationId($reservation)));
        $this->realtimePublisher->publishReservationChanged($reservation, 'reservation.confirmed');
        $this->realtimePublisher->publishAvailabilityChanged($reservation);

        return $reservation;
    }

    public function refuse(Reservation $reservation, User $actor, string $reason): Reservation
    {
        $this->assertReasonIsProvided($reason, 'Un motif de refus est obligatoire.');

        $reservation = $this->entityManager->wrapInTransaction(function () use ($reservation, $actor, $reason): Reservation {
            if ($reservation->getBookingStatus() !== BookingStatus::PENDING) {
                throw new \LogicException('Cette réservation ne peut plus être refusée.');
            }

            $reservation->setCancellationReason($reason);
            $this->transitionTo($reservation, BookingStatus::CANCELLED, $actor);
            $this->entityManager->flush();

            return $reservation;
        });

        $this->messageBus->dispatch(new BookingRefusedMessage($this->reservationId($reservation)));
        $this->realtimePublisher->publishReservationChanged($reservation, 'reservation.refused');

        return $reservation;
    }

    public function cancel(Reservation $reservation, ?User $actor, string $reason): Reservation
    {
        $this->assertReasonIsProvided($reason, 'Un motif d\'annulation est obligatoire.');

        $wasConfirmed = $reservation->getBookingStatus() === BookingStatus::CONFIRMED;
        $reservation = $this->cancelWithStatusMessage($reservation, $actor, $reason, 'Transition invalide');

        $this->messageBus->dispatch(new BookingCancelledMessage($this->reservationId($reservation)));
        $this->realtimePublisher->publishReservationChanged($reservation, 'reservation.cancelled');
        if ($wasConfirmed) {
            $this->realtimePublisher->publishAvailabilityChanged($reservation);
        }

        return $reservation;
    }

    public function expire(Reservation $reservation): Reservation
    {
        return $this->cancel($reservation, null, 'Délai de confirmation expiré (24h)');
    }

    public function markCompleted(Reservation $reservation): Reservation
    {
        $reservation = $this->entityManager->wrapInTransaction(function () use ($reservation): Reservation {
            if ($reservation->getBookingStatus() !== BookingStatus::CONFIRMED) {
                throw new \LogicException('Transition invalide');
            }

            $today = new \DateTimeImmutable('today');
            if ($reservation->getCheckoutDate() === null || $reservation->getCheckoutDate() >= $today) {
                throw new \LogicException('Transition invalide');
            }

            $this->transitionTo($reservation, BookingStatus::COMPLETED, null);
            $this->entityManager->flush();

            return $reservation;
        });

        $this->realtimePublisher->publishReservationChanged($reservation, 'reservation.completed');

        return $reservation;
    }

    private function confirmInsideTransaction(Reservation $reservation, ?User $actor): Reservation
    {
        if ($reservation->getBookingStatus() !== BookingStatus::PENDING) {
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

        $this->transitionTo($reservation, BookingStatus::CONFIRMED, $actor);
        $this->entityManager->flush();

        return $reservation;
    }

    private function cancelWithStatusMessage(Reservation $reservation, ?User $actor, string $reason, string $invalidMessage): Reservation
    {
        return $this->entityManager->wrapInTransaction(function () use ($reservation, $actor, $reason, $invalidMessage): Reservation {
            if (!in_array($reservation->getBookingStatus(), [BookingStatus::PENDING, BookingStatus::CONFIRMED], true)) {
                throw new \LogicException($invalidMessage);
            }

            $reservation->setCancellationReason($reason);
            $this->transitionTo($reservation, BookingStatus::CANCELLED, $actor);
            $this->entityManager->flush();

            return $reservation;
        });
    }

    private function transitionTo(Reservation $reservation, BookingStatus $newStatus, ?User $actor): void
    {
        $oldStatus = $reservation->getBookingStatus();
        if ($oldStatus === $newStatus) {
            throw new \LogicException('Transition invalide');
        }

        $reservation->setBookingStatus($newStatus);
        $reservation->setUpdatedAt(new \DateTimeImmutable());

        if ($newStatus === BookingStatus::CANCELLED) {
            if ($actor === null) {
                $reservation->setCancelledBy('system');
            } elseif ($reservation->getGuest() !== null && $actor->getId() === $reservation->getGuest()->getId()) {
                $reservation->setCancelledBy('guest');
            } else {
                $reservation->setCancelledBy('host');
            }
        }

        $this->appendHistory($reservation, $oldStatus, $newStatus, $actor);
    }

    private function appendHistory(Reservation $reservation, ?BookingStatus $oldStatus, BookingStatus $newStatus, ?User $actor): void
    {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus?->value);
        $history->setNewStatus($newStatus->value);
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

        $nights = (int) $checkin->diff($checkout)->days;
        $minimumStay = $property->getMinStayNights();
        if ($minimumStay !== null && $minimumStay > 0 && $nights < $minimumStay) {
            throw new \LogicException(sprintf('Ce logement impose un séjour minimum de %d nuit%s.', $minimumStay, $minimumStay > 1 ? 's' : ''));
        }
    }

    private function assertReasonIsProvided(string $reason, string $message): void
    {
        if (trim($reason) === '') {
            throw new \LogicException($message);
        }
    }

    private function reservationId(Reservation $reservation): string
    {
        $id = $reservation->getId();
        if ($id === null) {
            throw new \LogicException('Réservation invalide');
        }

        return $id->toRfc4122();
    }
}
