<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Message\ReservationCancelledMessage;
use App\Message\ReservationConfirmedMessage;
use App\Message\ReservationPendingMessage;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ReservationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReservationRepository $reservationRepository,
        private readonly PropertyAvailabilityRepository $availabilityRepository,
        private readonly \Symfony\Component\Messenger\MessageBusInterface $bus,
    ) {
    }

    public function isAvailable(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout, int $guests): bool
    {
        if ($property->getStatus() !== 'published') {
            return false;
        }

        if ($property->getMaxGuests() < $guests) {
            return false;
        }

        if ($this->availabilityRepository->countBlockedDays($property, $checkin, $checkout) > 0) {
            return false;
        }

        if ($this->reservationRepository->hasConfirmedConflict($property, $checkin, $checkout)) {
            return false;
        }

        return true;
    }

    public function create(Property $property, User $guest, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout, int $guests): Reservation
    {
        if (!$this->isAvailable($property, $checkin, $checkout, $guests)) {
            throw new BadRequestHttpException('Ces dates ne sont plus disponibles.');
        }

        $nights = $checkin->diff($checkout)->days;
        $pricePerNight = (float) $property->getPricePerNight();
        $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
        $securityDeposit = (float) ($property->getSecurityDeposit() ?? 0);
        $totalPrice = ($nights * $pricePerNight) + $cleaningFee;

        $reservation = new Reservation();
        $reservation->setProperty($property);
        $reservation->setGuest($guest);
        $reservation->setCheckinDate($checkin);
        $reservation->setCheckoutDate($checkout);
        $reservation->setGuestsCount($guests);
        $reservation->setCleaningFee((string) $cleaningFee);
        $reservation->setSecurityDeposit((string) $securityDeposit);
        $reservation->setTotalPrice((string) $totalPrice);
        $reservation->setCurrency('EUR');

        if ($property->isInstantBooking()) {
            $reservation->setStatus('confirmed');
        } else {
            $reservation->setStatus('pending');
        }

        $this->em->persist($reservation);

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus(null);
        $history->setNewStatus($reservation->getStatus());
        $history->setChangedBy($guest);
        $this->em->persist($history);

        $this->em->flush();

        if ($property->isInstantBooking()) {
            $this->bus->dispatch(new ReservationConfirmedMessage($reservation->getId()));
        } else {
            $this->bus->dispatch(new ReservationPendingMessage($reservation->getId()));
        }

        return $reservation;
    }

    public function confirm(Reservation $reservation, User $host): void
    {
        if ($reservation->getStatus() !== 'pending') {
            throw new BadRequestHttpException('Cette réservation ne peut pas être confirmée.');
        }

        $property = $reservation->getProperty();

        if ($this->reservationRepository->hasConfirmedConflict(
            $property,
            $reservation->getCheckinDate(),
            $reservation->getCheckoutDate(),
            $reservation
        )) {
            throw new BadRequestHttpException('Les dates ne sont plus disponibles.');
        }

        $this->changeStatus($reservation, 'confirmed', $host);
        $this->em->flush();

        $this->bus->dispatch(new ReservationConfirmedMessage($reservation->getId()));
    }

    public function cancel(Reservation $reservation, User $cancelledBy, string $reason): void
    {
        if (in_array($reservation->getStatus(), ['completed', 'cancelled'])) {
            throw new BadRequestHttpException('Cette réservation ne peut pas être annulée.');
        }

        $reservation->setCancellationReason($reason);
        $this->changeStatus($reservation, 'cancelled', $cancelledBy);
        $this->em->flush();

        $this->bus->dispatch(new ReservationCancelledMessage($reservation->getId()));
    }

    private function changeStatus(Reservation $reservation, string $newStatus, User $changedBy): void
    {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($reservation->getStatus());
        $history->setNewStatus($newStatus);
        $history->setChangedBy($changedBy);

        $reservation->setStatus($newStatus);
        $reservation->addStatusHistory($history);

        $this->em->persist($history);
    }
}