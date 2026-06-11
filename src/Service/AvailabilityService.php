<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;

class AvailabilityService
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly PropertyAvailabilityRepository $availabilityRepository,
    ) {}

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

        $conflicts = $this->reservationRepository->findConfirmedOverlapping($property, $checkin, $checkout);
        if (count($conflicts) > 0) {
            return false;
        }

        $blocks = $this->availabilityRepository->findOverlappingBlocks($property, $checkin, $checkout);
        if (count($blocks) > 0) {
            return false;
        }

        return true;
    }

    public function getUnavailabilityReason(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guestsCount,
    ): string {
        if ($property->getStatus() !== 'published') {
            return 'Ce logement n\'est pas disponible à la réservation.';
        }

        if ($guestsCount > $property->getMaxGuests()) {
            return sprintf('Ce logement accepte au maximum %d voyageur(s).', $property->getMaxGuests());
        }

        $conflicts = $this->reservationRepository->findConfirmedOverlapping($property, $checkin, $checkout);
        if (count($conflicts) > 0) {
            return 'Ces dates sont déjà réservées par un autre voyageur.';
        }

        $blocks = $this->availabilityRepository->findOverlappingBlocks($property, $checkin, $checkout);
        if (count($blocks) > 0) {
            $reason = $blocks[0]->getReason();

            return $reason
                ? sprintf('Le logement est indisponible sur cette période : %s', $reason)
                : 'Le logement est indisponible sur cette période.';
        }

        return 'Ces dates ne sont pas disponibles.';
    }
}
