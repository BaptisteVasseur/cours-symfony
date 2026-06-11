<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\User;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Central service for all availability-related logic.
 *
 * This is the single source of truth for checking whether a property is available
 * for a given date range and guest count. It combines two sources of unavailability:
 *   1. Manually blocked days (PropertyAvailability with isAvailable = false)
 *   2. Confirmed reservations (Reservation with status = 'confirmed')
 *
 * Default availability is "true by absence" — a missing row in
 * PropertyAvailability means the day is available.
 */
final class AvailabilityService
{
    public function __construct(
        private readonly PropertyAvailabilityRepository $availabilityRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Checks whether a property is available for the given parameters.
     *
     * A date range is available if and only if:
     *   1. The property status is 'published'
     *   2. The property has enough guest capacity
     *   3. No manually blocked days exist in the range
     *   4. No confirmed reservation overlaps the range
     */
    public function isAvailable(
        Property $property,
        \DateTimeInterface $checkin,
        \DateTimeInterface $checkout,
        int $guests,
    ): bool {
        // Check 1 — property must be published
        if ($property->getStatus() !== 'published') {
            return false;
        }

        // Check 2 — guest capacity
        if ($property->getMaxGuests() < $guests) {
            return false;
        }

        // Check 3 — no manually blocked days in the range
        $blockedDays = $this->availabilityRepository->findBlockedInRange($property, $checkin, $checkout);
        if (\count($blockedDays) > 0) {
            return false;
        }

        // Check 4 — no confirmed reservation overlaps the range
        $overlapping = $this->reservationRepository->findConfirmedOverlapping($property, $checkin, $checkout);
        if (\count($overlapping) > 0) {
            return false;
        }

        return true;
    }

    /**
     * Returns an array of DateTimeImmutable for the given month that are
     * either manually blocked OR covered by a confirmed reservation.
     *
     * Used to render the host calendar.
     *
     * @return \DateTimeImmutable[]
     */
    public function getUnavailableDates(Property $property, int $year, int $month): array
    {
        $unavailable = [];

        // Manually blocked days
        $availabilities = $this->availabilityRepository->findByPropertyAndMonth($property, $year, $month);
        foreach ($availabilities as $pa) {
            if (!$pa->isAvailable()) {
                $unavailable[] = $pa->getAvailableDate();
            }
        }

        // Days covered by confirmed reservations
        $from = new \DateTimeImmutable(\sprintf('%04d-%02d-01', $year, $month));
        $to = $from->modify('first day of next month');
        $confirmedReservations = $this->reservationRepository->findConfirmedOverlapping($property, $from, $to);

        foreach ($confirmedReservations as $reservation) {
            $current = \max($reservation->getCheckinDate(), $from);
            $end = \min($reservation->getCheckoutDate(), $to);

            while ($current < $end) {
                // Avoid duplicates with manually blocked days
                $dateStr = $current->format('Y-m-d');
                $alreadyPresent = false;
                foreach ($unavailable as $existing) {
                    if ($existing->format('Y-m-d') === $dateStr) {
                        $alreadyPresent = true;
                        break;
                    }
                }
                if (!$alreadyPresent) {
                    $unavailable[] = $current;
                }
                $current = $current->modify('+1 day');
            }
        }

        \usort($unavailable, static fn (\DateTimeImmutable $a, \DateTimeImmutable $b): int => $a <=> $b);

        return $unavailable;
    }

    /**
     * Blocks a date range for a property by creating PropertyAvailability records
     * with isAvailable = false.
     *
     * Skips days that are already blocked. Wrapped in a Doctrine transaction.
     */
    public function blockDates(
        Property $property,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        User $actor,
    ): void {
        $this->em->wrapInTransaction(function () use ($property, $from, $to): void {
            // Convert to DateTimeImmutable for iteration
            $fromImmutable = $from instanceof \DateTimeImmutable ? $from : \DateTimeImmutable::createFromMutable($from);
            $toImmutable = $to instanceof \DateTimeImmutable ? $to : \DateTimeImmutable::createFromMutable($to);

            $existingBlocked = $this->availabilityRepository->findBlockedInRange($property, $fromImmutable, $toImmutable);
            $blockedDates = [];
            foreach ($existingBlocked as $pa) {
                $blockedDates[$pa->getAvailableDate()->format('Y-m-d')] = true;
            }

            $current = $fromImmutable;
            while ($current < $toImmutable) {
                $dateKey = $current->format('Y-m-d');
                if (!isset($blockedDates[$dateKey])) {
                    $availability = new PropertyAvailability();
                    $availability->setProperty($property);
                    $availability->setAvailableDate($current);
                    $availability->setIsAvailable(false);
                    $this->em->persist($availability);
                    $blockedDates[$dateKey] = true;
                }
                $current = $current->modify('+1 day');
            }
        });
    }

    /**
     * Unblocks a date range by deleting PropertyAvailability records
     * with isAvailable = false in the given range.
     *
     * Only removes manually blocked days — does not touch confirmed reservations.
     */
    public function unblockDates(
        Property $property,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): void {
        $blockedDays = $this->availabilityRepository->findBlockedInRange($property, $from, $to);

        foreach ($blockedDays as $pa) {
            $this->em->remove($pa);
        }

        $this->em->flush();
    }
}
