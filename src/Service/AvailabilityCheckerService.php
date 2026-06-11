<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AvailabilitySchedule;
use App\Entity\Property;
use App\Repository\AvailabilityExceptionRepository;
use App\Repository\AvailabilityScheduleRepository;
use App\Repository\ReservationRepository;

class AvailabilityCheckerService
{
    public function __construct(
        private readonly AvailabilityScheduleRepository $scheduleRepo,
        private readonly AvailabilityExceptionRepository $exceptionRepo,
        private readonly ReservationRepository $reservationRepo,
    ) {
    }

    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): bool {
        return count($this->getViolations($property, $checkin, $checkout)) === 0;
    }

    /**
     * @return string[] Liste des raisons d'indisponibilité
     */
    public function getViolations(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): array {
        $violations = [];
        $nights = (int) $checkin->diff($checkout)->days;

        if ($nights < 1) {
            $violations[] = 'La durée de séjour doit être d\'au moins 1 nuit.';

            return $violations;
        }

        $schedule = $this->findMatchingSchedule($property, $checkin, $checkout);

        if ($schedule === null) {
            $violations[] = 'Les dates sélectionnées ne correspondent à aucune plage de disponibilité.';

            return $violations;
        }

        if ($nights < $schedule->getMinimumStay()) {
            $violations[] = sprintf(
                'La durée minimale de séjour est de %d nuit(s).',
                $schedule->getMinimumStay()
            );
        }

        if ($schedule->getMaximumStay() !== null && $nights > $schedule->getMaximumStay()) {
            $violations[] = sprintf(
                'La durée maximale de séjour est de %d nuit(s).',
                $schedule->getMaximumStay()
            );
        }

        $exceptions = $this->exceptionRepo->findForPropertyInRange($property, $checkin, $checkout);
        if (count($exceptions) > 0) {
            $violations[] = 'Une ou plusieurs dates de votre séjour sont bloquées.';
        }

        $conflicts = $this->reservationRepo->findConflictingReservations($property, $checkin, $checkout);
        if (count($conflicts) > 0) {
            $violations[] = 'Ces dates sont déjà réservées.';
        }

        return $violations;
    }

    /**
     * Trouve le schedule actif qui couvre toute la période.
     * Chaque jour du séjour (hors checkout) doit être couvert.
     */
    public function findMatchingSchedule(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
    ): ?AvailabilitySchedule {
        $schedules = $this->scheduleRepo->findActiveForProperty($property, $checkin, $checkout);

        if (empty($schedules)) {
            return null;
        }

        $current = $checkin;
        while ($current < $checkout) {
            $covered = false;
            foreach ($schedules as $schedule) {
                if ($schedule->coversDay($current)) {
                    $covered = true;
                    break;
                }
            }

            if (!$covered) {
                return null;
            }

            $current = $current->modify('+1 day');
        }

        usort($schedules, static fn (AvailabilitySchedule $a, AvailabilitySchedule $b): int => $a->getMinimumStay() <=> $b->getMinimumStay());

        return $schedules[0];
    }
}
