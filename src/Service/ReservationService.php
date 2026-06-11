<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Exception\UnavailableDatesException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;

final class ReservationService
{
    private const float SERVICE_FEE_RATE = 0.12;
    private const string EXCLUSION_VIOLATION_SQLSTATE = '23P01';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AvailabilityService $availability,
    ) {
    }

    public function create(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkinDate,
        \DateTimeImmutable $checkoutDate,
        int $guestsCount,
    ): Reservation {
        if (!$this->availability->isAvailable($property, $checkinDate, $checkoutDate, $guestsCount)) {
            throw new UnavailableDatesException();
        }

        $status = $property->isInstantBooking() ? 'confirmed' : 'pending';

        $reservation = new Reservation();
        $reservation->setProperty($property);
        $reservation->setGuest($guest);
        $reservation->setCheckinDate($checkinDate);
        $reservation->setCheckoutDate($checkoutDate);
        $reservation->setGuestsCount($guestsCount);
        $reservation->setStatus($status);
        $reservation->setCurrency('EUR');
        $this->applyPricing($reservation, $property);

        try {
            $this->em->wrapInTransaction(function () use ($reservation, $guest, $status): void {
                $this->em->persist($reservation);
                $this->logStatus($reservation, null, $status, $guest);
            });
        } catch (DriverException $exception) {
            if ($exception->getSQLState() === self::EXCLUSION_VIOLATION_SQLSTATE) {
                throw new UnavailableDatesException(previous: $exception);
            }

            throw $exception;
        }

        return $reservation;
    }

    private function applyPricing(Reservation $reservation, Property $property): void
    {
        $nights = (int) $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days;
        $subtotal = (float) $property->getPricePerNight() * $nights;
        $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
        $serviceFee = round($subtotal * self::SERVICE_FEE_RATE, 2);
        $total = round($subtotal + $cleaningFee + $serviceFee, 2);

        $reservation->setCleaningFee($cleaningFee > 0 ? (string) $cleaningFee : null);
        $reservation->setServiceFee((string) $serviceFee);
        $reservation->setSecurityDeposit($property->getSecurityDeposit());
        $reservation->setTotalPrice((string) $total);
    }

    private function logStatus(Reservation $reservation, ?string $oldStatus, string $newStatus, User $changedBy): void
    {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus);
        $history->setNewStatus($newStatus);
        $history->setChangedBy($changedBy);
        $reservation->addStatusHistory($history);
        $this->em->persist($history);
    }
}
