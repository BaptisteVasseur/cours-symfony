<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Message\ReservationNotificationMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class ReservationService
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {}

    public function createReservation(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
    ): Reservation {
        if (!$this->availabilityService->isAvailable($property, $checkin, $checkout, $guests)) {
            throw new \RuntimeException('Ces dates ne sont pas disponibles pour ce logement.');
        }

        $nights = (int) $checkin->diff($checkout)->days;
        $nightlyRate = (float) $property->getPricePerNight();
        $subtotal = $nightlyRate * $nights;
        $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
        $serviceFee = round($subtotal * 0.12, 2);
        $totalPrice = round($subtotal + $cleaningFee + $serviceFee, 2);

        $status = $property->isInstantBooking() ? 'confirmed' : 'pending';

        $reservation = new Reservation();
        $reservation->setProperty($property);
        $reservation->setGuest($guest);
        $reservation->setCheckinDate($checkin);
        $reservation->setCheckoutDate($checkout);
        $reservation->setGuestsCount($guests);
        $reservation->setStatus($status);
        $reservation->setTotalPrice((string) $totalPrice);
        $reservation->setCleaningFee($cleaningFee > 0 ? (string) $cleaningFee : null);
        $reservation->setServiceFee((string) $serviceFee);
        $reservation->setSecurityDeposit($property->getSecurityDeposit());
        $reservation->setCurrency('EUR');

        $this->em->persist($reservation);

        $history = $this->createStatusHistory($reservation, null, $status, $guest);
        $this->em->persist($history);

        $this->em->flush();

        $notifType = $status === 'confirmed'
            ? ReservationNotificationMessage::TYPE_CONFIRMED
            : ReservationNotificationMessage::TYPE_NEW;

        $this->bus->dispatch(new ReservationNotificationMessage((string) $reservation->getId(), $notifType));

        return $reservation;
    }

    public function confirm(Reservation $reservation, User $actor): void
    {
        $oldStatus = $reservation->getStatus();
        $reservation->setStatus('confirmed');

        $history = $this->createStatusHistory($reservation, $oldStatus, 'confirmed', $actor);
        $this->em->persist($history);

        $this->em->flush();

        $this->bus->dispatch(new ReservationNotificationMessage(
            (string) $reservation->getId(),
            ReservationNotificationMessage::TYPE_CONFIRMED,
        ));
    }

    public function cancel(Reservation $reservation, User $actor, string $reason): void
    {
        $oldStatus = $reservation->getStatus();
        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);

        $history = $this->createStatusHistory($reservation, $oldStatus, 'cancelled', $actor);
        $this->em->persist($history);

        $this->em->flush();

        $this->bus->dispatch(new ReservationNotificationMessage(
            (string) $reservation->getId(),
            ReservationNotificationMessage::TYPE_CANCELLED,
        ));
    }

    private function createStatusHistory(
        Reservation $reservation,
        ?string $oldStatus,
        string $newStatus,
        User $changedBy,
    ): ReservationStatusHistory {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus);
        $history->setNewStatus($newStatus);
        $history->setChangedBy($changedBy);

        return $history;
    }
}
