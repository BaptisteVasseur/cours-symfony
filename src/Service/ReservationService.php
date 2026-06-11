<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Message\ReservationCancelledMessage;
use App\Message\ReservationConfirmedMessage;
use App\Message\ReservationCreatedMessage;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ReservationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReservationRepository $reservationRepository,
        private readonly MessageBusInterface $bus,
    ) {}

    public function isAvailable(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout, int $guestsCount): bool
    {
        if ($property->getStatus() !== 'published') {
            return false;
        }

        if ($guestsCount > $property->getMaxGuests()) {
            return false;
        }

        $conflict = $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Reservation::class, 'r')
            ->where('r.property = :property')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.checkinDate < :checkout')
            ->andWhere('r.checkoutDate > :checkin')
            ->setParameter('property', $property)
            ->setParameter('statuses', ['confirmed', 'pending'])
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout)
            ->getQuery()
            ->getSingleScalarResult();

        if ($conflict > 0) {
            return false;
        }

        $blockedDay = $this->em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(PropertyAvailability::class, 'a')
            ->where('a.property = :property')
            ->andWhere('a.availableDate >= :checkin')
            ->andWhere('a.availableDate < :checkout')
            ->andWhere('a.isAvailable = false')
            ->setParameter('property', $property)
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout)
            ->getQuery()
            ->getSingleScalarResult();

        return $blockedDay === 0;
    }

    public function createReservation(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guestsCount,
    ): Reservation {
        if (!$this->isAvailable($property, $checkin, $checkout, $guestsCount)) {
            throw new \RuntimeException('Ce logement n\'est pas disponible pour ces dates.');
        }

        $nights = (int) $checkin->diff($checkout)->days;
        $pricePerNight = (float) $property->getPricePerNight();
        $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
        $serviceFee = round($pricePerNight * $nights * 0.12, 2);
        $totalPrice = round($pricePerNight * $nights + $cleaningFee + $serviceFee, 2);

        $reservation = new Reservation();
        $reservation->setProperty($property);
        $reservation->setGuest($guest);
        $reservation->setCheckinDate($checkin);
        $reservation->setCheckoutDate($checkout);
        $reservation->setGuestsCount($guestsCount);
        $reservation->setCleaningFee((string) $cleaningFee);
        $reservation->setServiceFee((string) $serviceFee);
        $reservation->setSecurityDeposit($property->getSecurityDeposit());
        $reservation->setTotalPrice((string) $totalPrice);
        $reservation->setCurrency('EUR');

        $initialStatus = $property->isInstantBooking() ? 'confirmed' : 'pending';
        $reservation->setStatus($initialStatus);

        $this->addStatusHistory($reservation, null, $initialStatus, $guest);

        $this->em->persist($reservation);
        $this->em->flush();

        if ($initialStatus === 'pending') {
            $this->bus->dispatch(new ReservationCreatedMessage($reservation->getId()));
        } elseif ($initialStatus === 'confirmed') {
            $this->bus->dispatch(new ReservationConfirmedMessage($reservation->getId()));
        }

        return $reservation;
    }

    public function confirm(Reservation $reservation, User $changedBy): void
    {
        if ($reservation->getStatus() !== 'pending') {
            throw new \RuntimeException('Seule une réservation en attente peut être confirmée.');
        }

        $this->transition($reservation, 'confirmed', $changedBy);

        $this->bus->dispatch(new ReservationConfirmedMessage($reservation->getId()));
    }

    public function cancel(Reservation $reservation, User $changedBy, string $reason): void
    {
        if (!in_array($reservation->getStatus(), ['pending', 'confirmed'], true)) {
            throw new \RuntimeException('Cette réservation ne peut plus être annulée.');
        }

        $reservation->setCancellationReason($reason);
        $this->transition($reservation, 'cancelled', $changedBy);
        $this->bus->dispatch(new ReservationCancelledMessage($reservation->getId()));
    }

    public function complete(Reservation $reservation, User $changedBy): void
    {
        if ($reservation->getStatus() !== 'confirmed') {
            throw new \RuntimeException('Seule une réservation confirmée peut être complétée.');
        }

        $this->transition($reservation, 'completed', $changedBy);
    }

    private function transition(Reservation $reservation, string $newStatus, User $changedBy): void
    {
        $oldStatus = $reservation->getStatus();
        $reservation->setStatus($newStatus);
        $this->addStatusHistory($reservation, $oldStatus, $newStatus, $changedBy);
        $this->em->flush();
    }

    private function addStatusHistory(Reservation $reservation, ?string $oldStatus, string $newStatus, User $changedBy): void
    {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus);
        $history->setNewStatus($newStatus);
        $history->setChangedBy($changedBy);
        $this->em->persist($history);
    }
}