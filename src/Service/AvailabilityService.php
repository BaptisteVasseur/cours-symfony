<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\BlockoutRepository;
use App\Repository\ReservationRepository;

final class AvailabilityService
{
    public function __construct(
        private readonly BlockoutRepository $blockoutRepository,
        private readonly ReservationRepository $reservationRepository,
    ) {}

    /**
     * Retourne true si la plage est disponible selon les 4 critères.
     *
     * @throws \InvalidArgumentException si checkout <= checkin ou guestsCount < 1
     */
    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guestsCount,
    ): bool {
        return $this->getUnavailabilityReasons($property, $checkin, $checkout, $guestsCount) === [];
    }

    /**
     * Retourne la liste des raisons rendant la plage indisponible.
     * Tableau vide = disponible.
     *
     * Les clés retournées possibles :
     *   - 'property_not_published'  : statut != published
     *   - 'blockout'                : au moins un blockout hôte couvre la plage
     *   - 'confirmed_overlap'       : au moins une réservation confirmed est en conflit
     *   - 'capacity_exceeded'       : maxGuests < guestsCount
     *
     * @return array<string, string>  clé => message lisible
     *
     * @throws \InvalidArgumentException si les arguments sont invalides
     */
    public function getUnavailabilityReasons(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guestsCount,
    ): array {
        $this->assertValidRange($checkin, $checkout, $guestsCount);

        $reasons = [];

        if ($property->getStatus() !== 'published') {
            $reasons['property_not_published'] = 'Le logement n\'est pas publié.';
        }

        if ($this->blockoutRepository->hasBlockoutInRange($property, $checkin, $checkout)) {
            $reasons['blockout'] = 'L\'hôte a bloqué une ou plusieurs dates sur cette période.';
        }

        if ($this->reservationRepository->hasConfirmedOverlapping($property, $checkin, $checkout)) {
            $reasons['confirmed_overlap'] = 'Une réservation confirmée existe déjà sur cette période.';
        }

        if ($property->getMaxGuests() !== null && $property->getMaxGuests() < $guestsCount) {
            $reasons['capacity_exceeded'] = sprintf(
                'La capacité maximale du logement est de %d voyageur(s), %d demandé(s).',
                $property->getMaxGuests(),
                $guestsCount,
            );
        }

        return $reasons;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function assertValidRange(
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guestsCount,
    ): void {
        if ($checkout <= $checkin) {
            throw new \InvalidArgumentException(
                'La date de départ doit être strictement postérieure à la date d\'arrivée.',
            );
        }

        if ($guestsCount < 1) {
            throw new \InvalidArgumentException(
                'Le nombre de voyageurs doit être d\'au moins 1.',
            );
        }
    }
}
