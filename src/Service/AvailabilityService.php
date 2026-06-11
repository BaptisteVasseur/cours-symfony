<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use App\Service\Exception\UnavailableDatesException;

/**
 * Implements the availability algorithm (spec A.2): a range [checkin, checkout) is bookable iff
 *   1. the property is published,
 *   2. no day in the range is host-blocked,
 *   3. no confirmed reservation overlaps the range,
 *   4. the property capacity covers the guest count.
 *
 * Each check is a single set-based query — never one query per night.
 */
final class AvailabilityService
{
    private const PUBLISHED = 'published';

    public function __construct(
        private readonly ReservationRepository $reservations,
        private readonly PropertyAvailabilityRepository $availability,
    ) {
    }

    public function isRangeAvailable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
        ?Reservation $exclude = null,
    ): bool {
        return $this->firstUnavailabilityReason($property, $checkin, $checkout, $guests, $exclude) === null;
    }

    /**
     * @throws UnavailableDatesException with an end-user (French) message when the range is not bookable
     */
    public function assertRangeAvailable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
        ?Reservation $exclude = null,
    ): void {
        $reason = $this->firstUnavailabilityReason($property, $checkin, $checkout, $guests, $exclude);

        if ($reason !== null) {
            throw new UnavailableDatesException($reason);
        }
    }

    private function firstUnavailabilityReason(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
        ?Reservation $exclude,
    ): ?string {
        $checkin = $checkin->setTime(0, 0);
        $checkout = $checkout->setTime(0, 0);

        if ($checkin >= $checkout) {
            return 'La date de départ doit être postérieure à la date d\'arrivée.';
        }

        if ($property->getStatus() !== self::PUBLISHED) {
            return 'Ce logement n\'est pas disponible à la réservation.';
        }

        if ($guests < 1) {
            return 'Il doit y avoir au moins un voyageur.';
        }

        if ($guests > (int) $property->getMaxGuests()) {
            return sprintf('Ce logement accepte au maximum %d voyageurs.', (int) $property->getMaxGuests());
        }

        if ($this->availability->countBlockedInRange($property, $checkin, $checkout) > 0) {
            return 'Certaines dates sélectionnées ont été bloquées par l\'hôte.';
        }

        if ($this->reservations->findConflicting($property, $checkin, $checkout, exclude: $exclude) !== []) {
            return 'Ces dates ne sont plus disponibles pour ce logement.';
        }

        return null;
    }
}
