<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;

final class AvailabilityService
{
    public function __construct(
        private readonly PropertyAvailabilityRepository $availabilityRepository,
        private readonly ReservationRepository $reservationRepository,
    ) {
    }

    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
        ?string $excludeReservationId = null,
    ): bool {
        if ($property->getStatus() !== 'published') {
            return false;
        }

        if ($guests > $property->getMaxGuests()) {
            return false;
        }

        // Vérifier qu'aucun jour de la plage n'est manuellement bloqué
        if ($this->availabilityRepository->countBlockedInRange($property, $checkin, $checkout) > 0) {
            return false;
        }

        // Vérifier qu'aucune réservation confirmed ne chevauche la plage
        if ($this->reservationRepository->countOverlapping($property, $checkin, $checkout, $excludeReservationId) > 0) {
            return false;
        }

        return true;
    }

    /**
     * Retourne les dates bloquées (manuellement + réservations) pour un mois donné.
     * Format retourné : ['Y-m-d' => 'blocked'|'reserved']
     *
     * @return array<string, string>
     */
    public function getOccupiedDatesForMonth(Property $property, int $year, int $month): array
    {
        $occupied = [];

        // Jours bloqués manuellement
        foreach ($this->availabilityRepository->findForMonth($property, $year, $month) as $pa) {
            if (!$pa->isAvailable()) {
                $occupied[$pa->getAvailableDate()->format('Y-m-d')] = 'blocked';
            }
        }

        // Jours couverts par des réservations confirmed
        $from = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month));
        $to = $from->modify('first day of next month');

        $reservations = $this->reservationRepository->countOverlapping($property, $from, $to) > 0
            ? $this->findConfirmedInMonth($property, $from, $to)
            : [];

        foreach ($reservations as $reservation) {
            $cursor = $reservation->getCheckinDate();
            while ($cursor < $reservation->getCheckoutDate()) {
                $key = $cursor->format('Y-m-d');
                if ($cursor >= $from && $cursor < $to) {
                    $occupied[$key] = 'reserved';
                }
                $cursor = $cursor->modify('+1 day');
            }
        }

        return $occupied;
    }

    /** @return list<\App\Entity\Reservation> */
    private function findConfirmedInMonth(Property $property, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->reservationRepository->createQueryBuilder('r')
            ->andWhere('r.property = :property')
            ->andWhere('r.status = :status')
            ->andWhere('r.checkinDate < :to')
            ->andWhere('r.checkoutDate > :from')
            ->setParameter('property', $property)
            ->setParameter('status', 'confirmed')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }
}
