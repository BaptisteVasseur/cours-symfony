<?php

declare(strict_types=1);

namespace App\Service\Availability;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;

/**
 * Implémente l'algorithme de disponibilité (énoncé §A.2). Une plage [checkin, checkout)
 * est disponible si et seulement si :
 *   1. le logement est publié ;
 *   2. aucun jour de la période n'est bloqué manuellement par l'hôte ;
 *   3. aucune réservation « confirmed » ne se superpose ;
 *   4. la capacité d'accueil couvre le nombre de voyageurs.
 *
 * Les contrôles 2 et 3 sont des requêtes ensemblistes (COUNT) : jamais d'itération
 * jour par jour, quelle que soit la durée du séjour.
 */
final class AvailabilityChecker
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly PropertyAvailabilityRepository $availabilityRepository,
    ) {
    }

    /**
     * @param Reservation|null $exclude Réservation à ignorer dans le contrôle d'overlap
     *                                  (utile lors d'une revalidation sur une résa existante).
     */
    public function check(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
        ?Reservation $exclude = null,
    ): AvailabilityResult {
        $reasons = [];

        if ($checkin >= $checkout) {
            return AvailabilityResult::unavailable(['La date de départ doit être postérieure à la date d\'arrivée.']);
        }

        if ($property->getStatus() !== 'published') {
            $reasons[] = 'Ce logement n\'est pas disponible à la réservation.';
        }

        if ($guests > (int) $property->getMaxGuests()) {
            $reasons[] = sprintf('Ce logement accepte au maximum %d voyageurs.', $property->getMaxGuests());
        }

        if ($this->availabilityRepository->hasBlockedNight($property, $checkin, $checkout)) {
            $reasons[] = 'Certaines dates de la période sont indisponibles.';
        }

        if ($this->reservationRepository->hasOverlap($property, $checkin, $checkout, ['confirmed'], $exclude)) {
            $reasons[] = 'Ces dates sont déjà réservées.';
        }

        return $reasons === [] ? AvailabilityResult::available() : AvailabilityResult::unavailable($reasons);
    }

    /**
     * Raccourci booléen pour les cas où le motif n'est pas nécessaire.
     */
    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
        ?Reservation $exclude = null,
    ): bool {
        return $this->check($property, $checkin, $checkout, $guests, $exclude)->available;
    }
}
