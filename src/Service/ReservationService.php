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
use App\Service\GoogleCalendarSyncService;

final readonly class ReservationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AvailabilityChecker $availabilityChecker,
        private BookingPriceCalculator $priceCalculator,
        private MessageBusInterface $messageBus,
        private GoogleCalendarSyncService $googleCalendarSyncService,
    ) {
    }

    public function create(Property $property, User $guest, \DateTimeImmutable $checkinDate, \DateTimeImmutable $checkoutDate, int $guestsCount): Reservation
    {
        $reservation = $this->entityManager->wrapInTransaction(function () use ($property, $guest, $checkinDate, $checkoutDate, $guestsCount): Reservation {
            $this->entityManager->lock($property, LockMode::PESSIMISTIC_WRITE);
            $this->availabilityChecker->assertAvailable($property, $checkinDate, $checkoutDate, $guestsCount);

            $price = $this->priceCalculator->calculate($property, $checkinDate, $checkoutDate);
            $status = $property->isInstantBooking() ? 'confirmed' : 'pending';

            $reservation = (new Reservation())
                ->setProperty($property)
                ->setGuest($guest)
                ->setCheckinDate($checkinDate)
                ->setCheckoutDate($checkoutDate)
                ->setGuestsCount($guestsCount)
                ->setStatus($status)
                ->setTotalPrice($price->total)
                ->setCleaningFee($price->cleaningFee)
                ->setServiceFee($price->serviceFee)
                ->setSecurityDeposit($price->securityDeposit)
                ->setCurrency($price->currency);

            $this->addHistory($reservation, null, $status, $guest);
            $this->entityManager->persist($reservation);

            return $reservation;
        });

        $this->messageBus->dispatch(new ReservationNotificationMessage((string) $reservation->getId(), $reservation->getStatus() ?? 'pending'));

        if ($reservation->getStatus() === 'confirmed') {
            $this->googleCalendarSyncService->pushReservation($reservation);
        }

        return $reservation;
    }

    public function confirm(Reservation $reservation, User $actor): void
    {
        $this->entityManager->wrapInTransaction(function () use ($reservation, $actor): void {
            $property = $reservation->getProperty();
            if ($property === null || $reservation->getCheckinDate() === null || $reservation->getCheckoutDate() === null || $reservation->getGuestsCount() === null) {
                throw new \DomainException('Réservation invalide.');
            }

            if ($reservation->getStatus() !== 'pending') {
                throw new \DomainException('Seule une demande en attente peut être acceptée.');
            }

            $this->entityManager->lock($property, LockMode::PESSIMISTIC_WRITE);
            $this->availabilityChecker->assertAvailable($property, $reservation->getCheckinDate(), $reservation->getCheckoutDate(), $reservation->getGuestsCount(), $reservation);

            $this->changeStatus($reservation, 'confirmed', $actor);
        });

        $this->messageBus->dispatch(new ReservationNotificationMessage((string) $reservation->getId(), 'confirmed'));

        $this->googleCalendarSyncService->pushReservation($reservation);
    }

    public function cancel(Reservation $reservation, User $actor, string $reason): void
    {
        if (!in_array($reservation->getStatus(), ['pending', 'confirmed'], true)) {
            throw new \DomainException('Cette réservation ne peut plus être annulée.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \DomainException('Le motif d\'annulation est obligatoire.');
        }

        $reservation->setCancellationReason($reason);
        $this->changeStatus($reservation, 'cancelled', $actor);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new ReservationNotificationMessage((string) $reservation->getId(), 'cancelled'));
    }

    private function changeStatus(Reservation $reservation, string $newStatus, User $actor): void
    {
        $oldStatus = $reservation->getStatus();
        $reservation->setStatus($newStatus);
        $this->addHistory($reservation, $oldStatus, $newStatus, $actor);
    }

    private function addHistory(Reservation $reservation, ?string $oldStatus, string $newStatus, User $actor): void
    {
        $history = (new ReservationStatusHistory())
            ->setReservation($reservation)
            ->setOldStatus($oldStatus)
            ->setNewStatus($newStatus)
            ->setChangedBy($actor);

        $reservation->addStatusHistory($history);
        $this->entityManager->persist($history);
    }
}
