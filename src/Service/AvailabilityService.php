<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

final class AvailabilityService
{
    public function __construct(
        private readonly PropertyAvailabilityRepository $availabilityRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
    ): bool {
        if ($property->getStatus() !== 'published') {
            return false;
        }

        if ($guests > ($property->getMaxGuests() ?? 0)) {
            return false;
        }

        if ($this->reservationRepository->countConfirmedConflicts($property, $checkin, $checkout) > 0) {
            return false;
        }

        if ($this->availabilityRepository->countBlockedInRange($property, $checkin, $checkout) > 0) {
            return false;
        }

        return true;
    }

    public function blockDates(
        Property $property,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?string $reason = null,
    ): void {
        $current = $from;
        while ($current < $to) {
            $existing = $this->availabilityRepository->findByPropertyAndDate($property, $current);
            if ($existing === null) {
                $availability = new PropertyAvailability();
                $availability->setProperty($property);
                $availability->setAvailableDate($current);
                $availability->setIsAvailable(false);
                $this->entityManager->persist($availability);
            } else {
                $existing->setIsAvailable(false);
            }
            $current = $current->modify('+1 day');
        }
        $this->entityManager->flush();
    }

    public function unblockDates(
        Property $property,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): void {
        $current = $from;
        while ($current < $to) {
            $existing = $this->availabilityRepository->findByPropertyAndDate($property, $current);
            if ($existing !== null && !$existing->isAvailable()) {
                $this->entityManager->remove($existing);
            }
            $current = $current->modify('+1 day');
        }
        $this->entityManager->flush();
    }

    /**
     * Returns an array keyed by date string 'Y-m-d' => bool (true = available, false = blocked).
     *
     * @return array<string, bool>
     */
    public function getBlockedDatesForMonth(Property $property, int $year, int $month): array
    {
        $entries = $this->availabilityRepository->findForMonth($property, $year, $month);
        $result = [];
        foreach ($entries as $entry) {
            if (!$entry->isAvailable()) {
                $result[$entry->getAvailableDate()->format('Y-m-d')] = false;
            }
        }

        return $result;
    }

    /**
     * Returns dates blocked by confirmed reservations for a given month.
     *
     * @return array<string, string>
     */
    public function getConfirmedReservationDatesForMonth(Property $property, int $year, int $month): array
    {
        $start = new \DateTimeImmutable(\sprintf('%d-%02d-01', $year, $month));
        $end = $start->modify('first day of next month');

        $reservations = $this->reservationRepository->findConfirmedForProperty($property);
        $result = [];
        foreach ($reservations as $reservation) {
            $day = $reservation->getCheckinDate();
            while ($day < $reservation->getCheckoutDate()) {
                if ($day >= $start && $day < $end) {
                    $result[$day->format('Y-m-d')] = 'confirmed';
                }
                $day = $day->modify('+1 day');
            }
        }

        return $result;
    }
}
