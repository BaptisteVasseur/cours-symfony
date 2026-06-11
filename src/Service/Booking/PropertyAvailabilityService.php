<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\Property;
use App\Exception\BookingException;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;

final class PropertyAvailabilityService
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private PropertyAvailabilityRepository $propertyAvailabilityRepository,
    ) {
    }

    public function assertBookable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guestsCount,
    ): void {
        if ($property->getStatus() !== 'published') {
            throw new BookingException('Ce logement n’est pas publié.');
        }

        if ($checkin >= $checkout) {
            throw new BookingException('La date de départ doit être postérieure à la date d’arrivée.');
        }

        if ($guestsCount < 1) {
            throw new BookingException('Le nombre de voyageurs est invalide.');
        }

        if ($guestsCount > $property->getMaxGuests()) {
            throw new BookingException(sprintf('Ce logement accepte au maximum %d voyageurs.', $property->getMaxGuests()));
        }

        if ($this->reservationRepository->hasConfirmedOverlap($property, $checkin, $checkout)) {
            throw new BookingException('Ces dates ne sont plus disponibles.');
        }

        $overrides = $this->propertyAvailabilityRepository->findForRange($property, $checkin, $checkout);

        foreach ($overrides as $override) {
            if (!$override->isAvailable()) {
                throw new BookingException('Une ou plusieurs nuits de cette période sont bloquées par l’hôte.');
            }
        }

        $nights = (int) $checkin->diff($checkout)->days;
        $minimumStay = 0;

        foreach ($overrides as $override) {
            $minimumStay = max($minimumStay, $override->getMinimumStay() ?? 0);
        }

        if ($minimumStay > 0 && $nights < $minimumStay) {
            throw new BookingException(sprintf('La durée minimale de séjour pour cette période est de %d nuits.', $minimumStay));
        }
    }
}
