<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;

final class AvailabilityChecker
{
    public function __construct(
        private readonly ReservationRepository $reservations,
        private readonly PropertyAvailabilityRepository $availabilities,
    ) {
    }

    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
        ?Reservation $exclude = null,
    ): bool {
        return $this->getUnavailabilityReason($property, $checkin, $checkout, $guests, $exclude) === null;
    }

    public function getUnavailabilityReason(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
        ?Reservation $exclude = null,
    ): ?string {
        if ($checkin >= $checkout) {
            return 'La date de départ doit être postérieure à la date d\'arrivée.';
        }

        if ($property->getStatus() !== 'published') {
            return 'Ce logement n\'est pas disponible à la réservation.';
        }

        if ($guests < 1 || $guests > (int) $property->getMaxGuests()) {
            return sprintf('Ce logement accepte au maximum %d voyageurs.', (int) $property->getMaxGuests());
        }

        if (\count($this->availabilities->findBlockedDatesInRange($property, $checkin, $checkout)) > 0) {
            return 'Certaines dates de la période sont bloquées par l\'hôte.';
        }

        if (\count($this->reservations->findConfirmedOverlapping($property, $checkin, $checkout, $exclude)) > 0) {
            return 'Ces dates sont déjà réservées.';
        }

        return null;
    }
}
