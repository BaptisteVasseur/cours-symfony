<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;

final class ReservationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReservationRepository $reservationRepository,
        private readonly PropertyAvailabilityRepository $availabilityRepository,
    ) {}

    /**
     * Checks availability using two single SQL queries:
     * - no overlapping confirmed reservation (semi-open interval [checkin, checkout))
     * - no day manually blocked by the host in PropertyAvailability
     */
    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): bool {
        return !$this->reservationRepository->hasOverlappingReservation($property, $checkin, $checkout)
            && !$this->availabilityRepository->hasBlockedDays($property, $checkin, $checkout);
    }

    /**
     * Creates a reservation inside a transaction with a pessimistic write lock on the
     * property row. The lock prevents two concurrent requests from booking the same
     * dates: the second request waits, then finds the slot taken and throws.
     *
     * The flush() happens inside the transaction so the commit and the persistence
     * are atomic. The caller dispatches the notification message AFTER this method
     * returns, guaranteeing the reservation exists in DB before the handler runs.
     *
     * @throws \RuntimeException if dates are no longer available
     */
    public function book(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guestsCount,
    ): Reservation {
        return $this->em->wrapInTransaction(function () use ($property, $guest, $checkin, $checkout, $guestsCount): Reservation {
            // Acquire a row-level write lock on the property to serialize concurrent bookings
            $this->em->lock($property, LockMode::PESSIMISTIC_WRITE);

            // Re-check availability inside the transaction (after the lock is held)
            if (!$this->isAvailable($property, $checkin, $checkout)) {
                throw new \RuntimeException('Ce logement n\'est plus disponible pour ces dates.');
            }

            $nights    = (int) $checkin->diff($checkout)->days;
            $subtotal  = (float) $property->getPricePerNight() * $nights;
            $cleaning  = (float) ($property->getCleaningFee() ?? 0);
            $service   = round($subtotal * 0.12, 2);

            $reservation = new Reservation();
            $reservation->setProperty($property);
            $reservation->setGuest($guest);
            $reservation->setCheckinDate($checkin);
            $reservation->setCheckoutDate($checkout);
            $reservation->setGuestsCount($guestsCount);
            $reservation->setStatus($property->isInstantBooking() ? 'confirmed' : 'pending');
            $reservation->setTotalPrice((string) round($subtotal + $cleaning + $service, 2));
            $reservation->setCleaningFee($cleaning > 0 ? (string) $cleaning : null);
            $reservation->setServiceFee((string) $service);
            $reservation->setSecurityDeposit($property->getSecurityDeposit());
            $reservation->setCurrency('EUR');

            $this->em->persist($reservation);
            $this->em->flush();

            return $reservation;
        });
    }
}
