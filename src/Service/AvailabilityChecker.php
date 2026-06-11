<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;

/**
 * Implémente l'algorithme de disponibilité (spécification A.2).
 *
 * Une plage de dates est disponible si et seulement si :
 *   1. Le logement est publié (status = "published").
 *   2. Aucun jour de la période n'a été manuellement bloqué par l'hôte.
 *   3. Aucune réservation au statut "confirmed" ne se superpose à ces dates.
 *   4. La capacité d'accueil est suffisante pour le nombre de voyageurs.
 */
final class AvailabilityChecker
{
    public function __construct(
        private readonly PropertyAvailabilityRepository $availabilityRepository,
        private readonly ReservationRepository $reservationRepository,
    ) {
    }

    /**
     * Indique si le logement est réservable pour la plage [checkin, checkout[ et le nombre de voyageurs donné.
     */
    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
    ): bool {
        return $this->getUnavailabilityReasons($property, $checkin, $checkout, $guests) === [];
    }

    /**
     * Retourne la liste des motifs d'indisponibilité (vide si la plage est disponible).
     * Utile pour afficher un message d'erreur précis au voyageur.
     *
     * @return list<string>
     */
    public function getUnavailabilityReasons(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
    ): array {
        $checkin = $checkin->setTime(0, 0);
        $checkout = $checkout->setTime(0, 0);
        $reasons = [];

        // Les dates doivent être cohérentes (au moins une nuit).
        if ($checkin >= $checkout) {
            $reasons[] = 'La date de départ doit être postérieure à la date d\'arrivée.';

            return $reasons;
        }

        // 1. Le logement doit être publié.
        if ($property->getStatus() !== 'published') {
            $reasons[] = 'Ce logement n\'est pas publié.';
        }

        // 4. La capacité d'accueil doit être suffisante.
        if ($guests < 1) {
            $reasons[] = 'Le nombre de voyageurs doit être d\'au moins 1.';
        } elseif ($guests > (int) $property->getMaxGuests()) {
            $reasons[] = sprintf('Ce logement accepte au maximum %d voyageurs.', (int) $property->getMaxGuests());
        }

        // On vérifie les nuits réservées : de la date d'arrivée à la veille du départ incluse.
        $lastNight = $checkout->modify('-1 day');

        // 2. Aucun jour de la période ne doit être manuellement bloqué par l'hôte.
        if ($this->availabilityRepository->hasBlockedDay($property, $checkin, $lastNight)) {
            $reasons[] = 'Certaines dates ont été bloquées par l\'hôte.';
        }

        // 3. Aucune réservation confirmée ne doit se superposer à ces dates.
        if ($this->reservationRepository->findConfirmedOverlapping($property, $checkin, $checkout) !== []) {
            $reasons[] = 'Ces dates sont déjà réservées.';
        }

        return $reasons;
    }
}
