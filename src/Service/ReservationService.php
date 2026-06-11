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
        private readonly EntityManagerInterface $em,
        private readonly AvailabilityService $availabilityService,
        private readonly MessageBusInterface $bus,
    ) {}

    public function createReservation(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guestsCount,
    ): Reservation {
        if (!$this->availabilityService->isAvailable($property, $checkin, $checkout, $guestsCount)) {
            throw new \RuntimeException('Ces dates ne sont pas disponibles.');
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
        $reservation->setGuestsCount($guestsCount);
        $reservation->setStatus($status);
        $reservation->setTotalPrice((string) $totalPrice);
        $reservation->setCleaningFee($cleaningFee > 0 ? (string) $cleaningFee : null);
        $reservation->setServiceFee((string) $serviceFee);
        $reservation->setSecurityDeposit($property->getSecurityDeposit());
        $reservation->setCurrency('EUR');

        $history = $this->buildHistory($reservation, null, $status, $guest);

        $this->em->persist($reservation);
        $this->em->persist($history);
        $this->em->flush();

        $this->bus->dispatch(new ReservationNotificationMessage((string) $reservation->getId(), $status));

        return $reservation;
    }

    public function confirmReservation(Reservation $reservation, User $changedBy): void
    {
        $this->transition($reservation, 'confirmed', $changedBy);
        $this->bus->dispatch(new ReservationNotificationMessage((string) $reservation->getId(), 'confirmed'));
    }

    public function cancelReservation(Reservation $reservation, User $changedBy, string $reason): void
    {
        $reservation->setCancellationReason($reason);
        $this->transition($reservation, 'cancelled', $changedBy);
        $this->bus->dispatch(new ReservationNotificationMessage((string) $reservation->getId(), 'cancelled', $reason));
    }

    private function transition(Reservation $reservation, string $newStatus, User $changedBy): void
    {
        $old = $reservation->getStatus();
        $reservation->setStatus($newStatus);
        $history = $this->buildHistory($reservation, $old, $newStatus, $changedBy);
        $this->em->persist($history);
        $this->em->flush();
    }

    private function buildHistory(
        Reservation $reservation,
        ?string $old,
        string $new,
        User $changedBy,
    ): ReservationStatusHistory {
        $h = new ReservationStatusHistory();
        $h->setReservation($reservation);
        $h->setOldStatus($old);
        $h->setNewStatus($new);
        $h->setChangedBy($changedBy);

        return $h;
    }
}
