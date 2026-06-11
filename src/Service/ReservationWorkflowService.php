<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Message\ReservationNotificationMessage;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ReservationWorkflowService
{
    public const string EVENT_PENDING_CREATED = 'pending_created';
    public const string EVENT_CONFIRMED_CREATED = 'confirmed_created';
    public const string EVENT_CONFIRMED = 'confirmed';
    public const string EVENT_REFUSED = 'refused';
    public const string EVENT_CANCELLED = 'cancelled';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BookingAvailabilityService $bookingAvailabilityService,
        private BookingPricingService $bookingPricingService,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function createReservation(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guestsCount,
    ): Reservation {
        $reservation = new Reservation();

        $this->entityManager->wrapInTransaction(function () use (
            $property,
            $guest,
            $checkin,
            $checkout,
            $guestsCount,
            $reservation,
        ): void {
            $this->lockProperty($property);

            $availability = $this->bookingAvailabilityService->check($property, $checkin, $checkout, $guestsCount);
            if (!$availability->isAvailable()) {
                throw new \DomainException($availability->getPrimaryReasonCode() ?? 'unavailable');
            }

            $pricing = $this->bookingPricingService->calculate($property, $checkin, $checkout);
            $status = $property->isInstantBooking() ? 'confirmed' : 'pending';

            $reservation->setProperty($property);
            $reservation->setGuest($guest);
            $reservation->setCheckinDate($checkin);
            $reservation->setCheckoutDate($checkout);
            $reservation->setGuestsCount($guestsCount);
            $reservation->setStatus($status);
            $reservation->setTotalPrice((string) $pricing->getTotalPrice());
            $reservation->setCleaningFee($pricing->getCleaningFee() > 0 ? (string) $pricing->getCleaningFee() : null);
            $reservation->setServiceFee((string) $pricing->getServiceFee());
            $reservation->setSecurityDeposit($property->getSecurityDeposit());
            $reservation->setCurrency('EUR');

            $this->entityManager->persist($reservation);
            $this->appendHistory($reservation, null, $status, $guest);
            $this->entityManager->flush();
        });

        $event = $reservation->getStatus() === 'confirmed'
            ? self::EVENT_CONFIRMED_CREATED
            : self::EVENT_PENDING_CREATED;

        $this->messageBus->dispatch(new ReservationNotificationMessage(
            (string) $reservation->getId(),
            $event,
        ));

        return $reservation;
    }

    public function acceptReservation(Reservation $reservation, User $actor): Reservation
    {
        $this->entityManager->wrapInTransaction(function () use ($reservation, $actor): void {
            $this->lockProperty($reservation->getProperty());
            $this->lockReservation($reservation);

            if ($reservation->getStatus() !== 'pending') {
                throw new \DomainException('Seules les réservations en attente peuvent être acceptées.');
            }

            $property = $reservation->getProperty();
            $checkin = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();
            $guestsCount = $reservation->getGuestsCount();

            if ($property === null || $checkin === null || $checkout === null || $guestsCount === null) {
                throw new \LogicException('Réservation incomplète.');
            }

            $availability = $this->bookingAvailabilityService->check(
                $property,
                $checkin,
                $checkout,
                $guestsCount,
                $reservation,
            );

            if (!$availability->isAvailable()) {
                throw new \DomainException('Cette demande ne peut plus être confirmée car les dates ne sont plus disponibles.');
            }

            $oldStatus = $reservation->getStatus();
            $reservation->setStatus('confirmed');
            $reservation->setCancellationReason(null);
            $this->appendHistory($reservation, $oldStatus, 'confirmed', $actor);
            $this->entityManager->flush();
        });

        $this->messageBus->dispatch(new ReservationNotificationMessage(
            (string) $reservation->getId(),
            self::EVENT_CONFIRMED,
        ));

        return $reservation;
    }

    public function refuseReservation(Reservation $reservation, User $actor, string $reason): Reservation
    {
        $this->entityManager->wrapInTransaction(function () use ($reservation, $actor, $reason): void {
            $this->lockReservation($reservation);

            if ($reservation->getStatus() !== 'pending') {
                throw new \DomainException('Seules les réservations en attente peuvent être refusées.');
            }

            $oldStatus = $reservation->getStatus();
            $reservation->setStatus('cancelled');
            $reservation->setCancellationReason($reason);
            $this->appendHistory($reservation, $oldStatus, 'cancelled', $actor);
            $this->entityManager->flush();
        });

        $this->messageBus->dispatch(new ReservationNotificationMessage(
            (string) $reservation->getId(),
            self::EVENT_REFUSED,
        ));

        return $reservation;
    }

    public function cancelReservation(Reservation $reservation, User $actor, string $reason): Reservation
    {
        $this->entityManager->wrapInTransaction(function () use ($reservation, $actor, $reason): void {
            $this->lockReservation($reservation);

            if (!in_array($reservation->getStatus(), ['pending', 'confirmed'], true)) {
                throw new \DomainException('Cette réservation ne peut plus être annulée.');
            }

            $oldStatus = $reservation->getStatus();
            $reservation->setStatus('cancelled');
            $reservation->setCancellationReason($reason);
            $this->appendHistory($reservation, $oldStatus, 'cancelled', $actor);
            $this->entityManager->flush();
        });

        $this->messageBus->dispatch(new ReservationNotificationMessage(
            (string) $reservation->getId(),
            self::EVENT_CANCELLED,
        ));

        return $reservation;
    }

    private function appendHistory(
        Reservation $reservation,
        ?string $oldStatus,
        string $newStatus,
        User $changedBy,
    ): void {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus);
        $history->setNewStatus($newStatus);
        $history->setChangedBy($changedBy);

        $this->entityManager->persist($history);
    }

    private function lockProperty(?Property $property): void
    {
        if ($property === null) {
            throw new \LogicException('Logement introuvable.');
        }

        $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Property::class, 'p')
            ->andWhere('p = :property')
            ->setParameter('property', $property)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getSingleResult();
    }

    private function lockReservation(Reservation $reservation): void
    {
        $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(Reservation::class, 'r')
            ->andWhere('r = :reservation')
            ->setParameter('reservation', $reservation)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getSingleResult();
    }
}
