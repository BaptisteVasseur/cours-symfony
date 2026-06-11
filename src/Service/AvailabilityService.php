<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Repository\PropertyUnavailabilityRepository;
use App\Repository\ReservationRepository;

final class AvailabilityService
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly PropertyUnavailabilityRepository $unavailabilityRepository,
    ) {
    }

    public function isAvailable(
        Property $property,
        \DateTimeInterface $checkIn,
        \DateTimeInterface $checkOut,
        int $guests,
        ?Reservation $excludeReservation = null,
    ): bool {
        if ($property->getStatus() !== 'published') {
            return false;
        }

        if ($property->getMaxGuests() < $guests) {
            return false;
        }

        $propertyId = (string) $property->getId();
        $start = $checkIn instanceof \DateTimeImmutable
            ? $checkIn
            : \DateTimeImmutable::createFromInterface($checkIn);
        $end = $checkOut instanceof \DateTimeImmutable
            ? $checkOut
            : \DateTimeImmutable::createFromInterface($checkOut);

        $blocked = $this->unavailabilityRepository->findOverlapping($propertyId, $start, $end);
        if (count($blocked) > 0) {
            return false;
        }

        $conflicts = $this->reservationRepository->findConfirmedOverlapping(
            $property,
            $start,
            $end,
            $excludeReservation,
        );

        return count($conflicts) === 0;
    }

    /**
     * @return list<array{type: string, start: \DateTimeImmutable, end: \DateTimeImmutable, label: string}>
     */
    public function getBlockedRanges(Property $property): array
    {
        $ranges = [];

        foreach ($this->reservationRepository->findConfirmedByProperty($property) as $reservation) {
            $checkin = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();
            if ($checkin === null || $checkout === null) {
                continue;
            }

            $ranges[] = [
                'type' => 'reservation',
                'start' => $checkin,
                'end' => $checkout,
                'label' => 'Réservation confirmée',
            ];
        }

        $unavailabilities = $this->unavailabilityRepository->findByProperty($property);

        foreach ($unavailabilities as $unavailability) {
            $start = $unavailability->getStartDate();
            $end = $unavailability->getEndDate();
            if ($start === null || $end === null) {
                continue;
            }

            $ranges[] = [
                'type' => 'blocked',
                'start' => $start,
                'end' => $end,
                'label' => $unavailability->getReason() ?? 'Indisponible',
            ];
        }

        usort($ranges, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        return $ranges;
    }
}
