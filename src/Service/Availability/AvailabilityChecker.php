<?php

declare(strict_types=1);

namespace App\Service\Availability;

use App\Entity\Property;
use App\Repository\PropertyUnavailabilityRepository;
use App\Repository\ReservationRepository;
use App\Service\Availability\Exception\PropertyNotAvailableException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Source de vérité unique de la disponibilité (spec A.2).
 *
 * Convention de plage : [checkin inclus, checkout exclu). Deux plages se chevauchent
 * ssi (debutA < finB) ET (debutB < finA).
 */
final class AvailabilityChecker
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReservationRepository $reservationRepository,
        private readonly PropertyUnavailabilityRepository $unavailabilityRepository,
    ) {
    }

    /**
     * Évalue la disponibilité sans verrou (lecture seule). Renvoie un motif structuré en cas d'échec.
     */
    public function check(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
    ): AvailabilityResult {
        $this->assertValidRequest($checkin, $checkout, $guests);

        if ($property->getStatus() !== 'published') {
            return AvailabilityResult::unavailable(AvailabilityFailureReason::NON_PUBLIE);
        }

        if ($this->unavailabilityRepository->hasOverlap($property, $checkin, $checkout)) {
            return AvailabilityResult::unavailable(AvailabilityFailureReason::JOURS_BLOQUES);
        }

        if ($this->reservationRepository->hasConfirmedOverlap($property, $checkin, $checkout)) {
            return AvailabilityResult::unavailable(AvailabilityFailureReason::CHEVAUCHEMENT);
        }

        if ($guests > (int) $property->getMaxGuests()) {
            return AvailabilityResult::unavailable(AvailabilityFailureReason::CAPACITE_INSUFFISANTE);
        }

        return AvailabilityResult::available();
    }

    /**
     * Vérification + opération atomiques contre la concurrence (100 % Doctrine).
     *
     * Pose un verrou pessimiste en écriture sur la Property, re-vérifie A.2, puis exécute
     * $onAvailable DANS la même transaction (le verrou est conservé jusqu'au commit), ce qui
     * rend la double-réservation impossible même sous requêtes simultanées.
     *
     * @template T
     * @param callable():T $onAvailable
     *
     * @return T
     *
     * @throws PropertyNotAvailableException si la période n'est pas disponible
     */
    public function assertAvailableWithLock(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
        callable $onAvailable,
    ): mixed {
        return $this->entityManager->wrapInTransaction(function () use ($property, $checkin, $checkout, $guests, $onAvailable) {
            $this->entityManager->lock($property, LockMode::PESSIMISTIC_WRITE);

            $result = $this->check($property, $checkin, $checkout, $guests);
            if (!$result->isAvailable()) {
                throw new PropertyNotAvailableException($result);
            }

            return $onAvailable();
        });
    }

    private function assertValidRequest(\DateTimeImmutable $checkin, \DateTimeImmutable $checkout, int $guests): void
    {
        if ($checkout <= $checkin) {
            throw new \InvalidArgumentException('La date de départ doit être strictement postérieure à la date d\'arrivée.');
        }

        if ($guests < 1) {
            throw new \InvalidArgumentException('Le nombre de voyageurs doit être au moins égal à 1.');
        }

        if ($checkin < new \DateTimeImmutable('today')) {
            throw new \InvalidArgumentException('La période ne peut pas commencer dans le passé.');
        }
    }
}
