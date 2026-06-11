<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Exception\UnavailableDatesException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class BookingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AvailabilityChecker $availabilityChecker,
    ) {
    }

    public function book(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
    ): Reservation {
        return $this->em->wrapInTransaction(function () use ($property, $guest, $checkin, $checkout, $guests): Reservation {
            $this->em->lock($property, LockMode::PESSIMISTIC_WRITE);

            if (!$this->availabilityChecker->isAvailable($property, $checkin, $checkout, $guests)) {
                throw new UnavailableDatesException('Ces dates ne sont plus disponibles.');
            }

            $nights = (int) $checkin->diff($checkout)->days;
            $subtotal = (float) $property->getPricePerNight() * $nights;
            $cleaningFee = (float) ($property->getCleaningFee() ?? '0');
            $serviceFee = round($subtotal * 0.12, 2);
            $total = round($subtotal + $cleaningFee + $serviceFee, 2);

            $status = $property->isInstantBooking() ? 'confirmed' : 'pending';

            $reservation = (new Reservation())
                ->setProperty($property)
                ->setGuest($guest)
                ->setCheckinDate($checkin)
                ->setCheckoutDate($checkout)
                ->setGuestsCount($guests)
                ->setStatus($status)
                ->setTotalPrice(number_format($total, 2, '.', ''))
                ->setCleaningFee($cleaningFee > 0 ? number_format($cleaningFee, 2, '.', '') : null)
                ->setServiceFee(number_format($serviceFee, 2, '.', ''))
                ->setSecurityDeposit($property->getSecurityDeposit())
                ->setCurrency('EUR');

            $history = (new ReservationStatusHistory())
                ->setReservation($reservation)
                ->setOldStatus(null)
                ->setNewStatus($status)
                ->setChangedBy($guest)
                ->setCreatedAt(new \DateTimeImmutable());
            $reservation->addStatusHistory($history);

            $this->em->persist($reservation);
            $this->em->persist($history);

            return $reservation;
        });
    }
}