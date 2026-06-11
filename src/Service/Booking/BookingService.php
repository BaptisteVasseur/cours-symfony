<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Service\Availability\AvailabilityChecker;
use App\Service\Mailer\ReservationMailer;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class BookingService
{
    private const SERVICE_FEE_RATE = 0.12;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AvailabilityChecker $availabilityChecker,
        private readonly ReservationMailer $reservationMailer,
    ) {
    }

    public function book(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
    ): Reservation {
        if ($property->isInstantBooking()) {
            $reservation = $this->createInstantBooking($property, $guest, $checkin, $checkout, $guests);
        } else {
            $reservation = $this->createPendingRequest($property, $guest, $checkin, $checkout, $guests);
        }

        if ($reservation->getStatus() === 'confirmed') {
            $this->reservationMailer->sendBookingConfirmed($reservation);
        } else {
            $this->reservationMailer->sendNewRequestToHost($reservation);
        }

        return $reservation;
    }

    public function acceptReservation(Reservation $reservation, User $host): Reservation
    {
        if ($reservation->getStatus() !== 'pending') {
            throw new \DomainException('Seules les demandes en attente peuvent être acceptées.');
        }

        $property = $reservation->getProperty();
        if (!$property instanceof Property) {
            throw new \DomainException('Réservation sans logement associé.');
        }

        $this->em->wrapInTransaction(function () use ($reservation, $property, $host): void {
            $this->em->lock($property, LockMode::PESSIMISTIC_WRITE);

            $result = $this->availabilityChecker->check(
                $property,
                $reservation->getCheckinDate(),
                $reservation->getCheckoutDate(),
                (int) $reservation->getGuestsCount(),
                $reservation,
            );

            if (!$result->available) {
                throw new BookingUnavailableException($result->reason);
            }

            $reservation->setStatus('confirmed');
            $this->em->persist($this->buildHistory($reservation, 'pending', 'confirmed', $host));
            $this->em->flush();
        });

        $this->reservationMailer->sendBookingConfirmed($reservation);

        return $reservation;
    }

    public function refuseReservation(Reservation $reservation, User $host, string $reason): Reservation
    {
        if ($reservation->getStatus() !== 'pending') {
            throw new \DomainException('Seules les demandes en attente peuvent être refusées.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \DomainException('Le motif de refus est obligatoire.');
        }

        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);
        $this->em->persist($this->buildHistory($reservation, 'pending', 'cancelled', $host));
        $this->em->flush();

        $this->reservationMailer->sendReservationRefused($reservation);

        return $reservation;
    }

    private function createPendingRequest(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
    ): Reservation {
        $result = $this->availabilityChecker->check($property, $checkin, $checkout, $guests);
        if (!$result->available) {
            throw new BookingUnavailableException($result->reason);
        }

        $reservation = $this->buildReservation($property, $guest, $checkin, $checkout, $guests, 'pending');

        $this->em->persist($reservation);
        $this->em->persist($this->buildInitialHistory($reservation, $guest));
        $this->em->flush();

        return $reservation;
    }

    private function createInstantBooking(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
    ): Reservation {
        return $this->em->wrapInTransaction(function () use ($property, $guest, $checkin, $checkout, $guests): Reservation {
            $this->em->lock($property, LockMode::PESSIMISTIC_WRITE);

            $result = $this->availabilityChecker->check($property, $checkin, $checkout, $guests);
            if (!$result->available) {
                throw new BookingUnavailableException($result->reason);
            }

            $reservation = $this->buildReservation($property, $guest, $checkin, $checkout, $guests, 'confirmed');

            $this->em->persist($reservation);
            $this->em->persist($this->buildInitialHistory($reservation, $guest));
            $this->em->flush();

            return $reservation;
        });
    }

    private function buildReservation(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
        string $status,
    ): Reservation {
        $nights = (int) $checkin->diff($checkout)->days;
        $nightlyRate = (float) $property->getPricePerNight();
        $subtotal = $nightlyRate * $nights;
        $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
        $serviceFee = round($subtotal * self::SERVICE_FEE_RATE, 2);
        $totalPrice = round($subtotal + $cleaningFee + $serviceFee, 2);

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

        return $reservation;
    }

    private function buildInitialHistory(Reservation $reservation, User $changedBy): ReservationStatusHistory
    {
        return $this->buildHistory($reservation, null, (string) $reservation->getStatus(), $changedBy);
    }

    private function buildHistory(
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
