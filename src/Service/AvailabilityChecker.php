<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;

final readonly class AvailabilityChecker
{
    public function __construct(
        private PropertyAvailabilityRepository $availabilityRepository,
        private ReservationRepository $reservationRepository,
        private BookingPriceCalculator $priceCalculator,
    ) {
    }

    public function assertAvailable(
        Property $property,
        \DateTimeImmutable $checkinDate,
        \DateTimeImmutable $checkoutDate,
        int $guestsCount,
        ?Reservation $ignoredReservation = null,
    ): void {
        if ($property->getStatus() !== 'published') {
            throw new \DomainException('Ce logement n\'est pas disponible à la réservation.');
        }

        if ($checkoutDate <= $checkinDate) {
            throw new \DomainException('La date de départ doit être postérieure à la date d\'arrivée.');
        }

        if ($guestsCount < 1) {
            throw new \DomainException('Le nombre de voyageurs doit être supérieur à zéro.');
        }

        if ($guestsCount > (int) $property->getMaxGuests()) {
            throw new \DomainException('Le logement ne peut pas accueillir autant de voyageurs.');
        }

        $propertyId = (string) $property->getId();
        if ($this->availabilityRepository->countUnavailableDays($propertyId, $checkinDate, $checkoutDate) > 0) {
            throw new \DomainException('Une ou plusieurs nuits sont indisponibles sur cette période.');
        }

        if ($this->reservationRepository->hasConfirmedOverlap($propertyId, $checkinDate, $checkoutDate, $ignoredReservation)) {
            throw new \DomainException('Ces dates sont déjà réservées.');
        }

        $minimumStay = $this->availabilityRepository->findMaximumMinimumStay($propertyId, $checkinDate, $checkoutDate);
        if ($minimumStay !== null && $this->priceCalculator->countNights($checkinDate, $checkoutDate) < $minimumStay) {
            throw new \DomainException(sprintf('La durée minimale de séjour est de %d nuit(s).', $minimumStay));
        }
    }
}
