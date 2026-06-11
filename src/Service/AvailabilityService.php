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
    ) {}

    /**
     * Une plage est disponible si :
     * 1. Le logement est publié
     * 2. Aucun jour n'est manuellement bloqué par l'hôte
     * 3. Aucune réservation confirmée ne chevauche les dates
     * 4. La capacité est suffisante
     */
    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guestsCount,
    ): bool {
        if ($property->getStatus() !== 'published') {
            return false;
        }

        if ($guestsCount > $property->getMaxGuests()) {
            return false;
        }

        if ($this->availabilityRepository->hasBlockedDayInRange($property, $checkin, $checkout)) {
            return false;
        }

        if ($this->reservationRepository->hasOverlappingConfirmed($property, $checkin, $checkout)) {
            return false;
        }

        return true;
    }

    /**
     * Retourne la liste des dates bloquées (hôte ou réservation confirmée) pour un mois donné.
     * Utilisé par le calendrier hôte.
     *
     * @return list<string> dates au format 'Y-m-d'
     */
    public function getBlockedDatesForMonth(Property $property, int $year, int $month): array
    {
        $firstDay = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $lastDay = $firstDay->modify('last day of this month');

        $blocked = [];

        $manualBlocks = $this->availabilityRepository->findBlockedInRange($property, $firstDay, $lastDay);
        foreach ($manualBlocks as $block) {
            $blocked[] = $block->getAvailableDate()->format('Y-m-d');
        }

        $confirmedPeriods = $this->reservationRepository->findConfirmedInRange($property, $firstDay, $lastDay);
        foreach ($confirmedPeriods as $reservation) {
            $cursor = $reservation->getCheckinDate();
            while ($cursor < $reservation->getCheckoutDate()) {
                $blocked[] = $cursor->format('Y-m-d');
                $cursor = $cursor->modify('+1 day');
            }
        }

        return array_unique($blocked);
    }
}
