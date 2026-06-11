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

    /**
     * Retourne les périodes indisponibles d'un logement (à partir d'aujourd'hui),
     * issues des jours bloqués par l'hôte ET des séjours confirmés, regroupées en plages.
     *
     * @return list<array{start: \DateTimeImmutable, end: \DateTimeImmutable}>
     *         Chaque période est inclusive sur le début et exclusive sur la fin (jour de départ).
     */
    public function getUnavailablePeriods(Property $property, ?\DateTimeImmutable $from = null, int $monthsAhead = 12): array
    {
        $from = ($from ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $to = $from->modify(sprintf('+%d months', $monthsAhead));

        /** @var array<string, \DateTimeImmutable> $nights nuits indisponibles, indexées 'Y-m-d' */
        $nights = [];

        // Jours manuellement bloqués par l'hôte.
        foreach ($this->availabilityRepository->findBlockedBetween($property, $from, $to) as $availability) {
            $day = $availability->getAvailableDate()->setTime(0, 0);
            $nights[$day->format('Y-m-d')] = $day;
        }

        // Nuits occupées par des réservations confirmées (de l'arrivée à la veille du départ).
        foreach ($this->reservationRepository->findConfirmedForProperty($property) as $reservation) {
            $checkin = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();
            if ($checkin === null || $checkout === null) {
                continue;
            }
            $night = $checkin->setTime(0, 0);
            $lastNight = $checkout->modify('-1 day')->setTime(0, 0);
            if ($night < $from) {
                $night = $from;
            }
            while ($night <= $lastNight && $night <= $to) {
                $nights[$night->format('Y-m-d')] = $night;
                $night = $night->modify('+1 day');
            }
        }

        if ($nights === []) {
            return [];
        }

        ksort($nights);

        // Regroupement des nuits consécutives en périodes.
        $periods = [];
        $start = null;
        $prev = null;
        foreach ($nights as $day) {
            if ($start === null) {
                $start = $day;
                $prev = $day;
                continue;
            }
            if ($day->getTimestamp() === $prev->modify('+1 day')->getTimestamp()) {
                $prev = $day;
                continue;
            }
            $periods[] = ['start' => $start, 'end' => $prev->modify('+1 day')];
            $start = $day;
            $prev = $day;
        }
        $periods[] = ['start' => $start, 'end' => $prev->modify('+1 day')];

        return $periods;
    }
}
