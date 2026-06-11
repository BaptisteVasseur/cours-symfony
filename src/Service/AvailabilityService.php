<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\Reservation;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AvailabilityService
{
    public function __construct(
        private PropertyAvailabilityRepository $availabilityRepository,
        private ReservationRepository $reservationRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function isAvailable(
        Property $property,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        int $guests,
        ?Reservation $excludedReservation = null,
    ): bool {
        if ($property->getStatus() !== 'published') {
            return false;
        }

        if ($start >= $end) {
            return false;
        }

        if ($guests < 1 || $property->getMaxGuests() === null || $guests > $property->getMaxGuests()) {
            return false;
        }

        if ($this->availabilityRepository->hasBlockedOverlap($property, $start, $end)) {
            return false;
        }

        return !$this->reservationRepository->hasConfirmedOverlap($property, $start, $end, $excludedReservation);
    }

    public function blockPeriod(
        Property $property,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?string $reason,
    ): PropertyAvailability {
        if ($start >= $end) {
            throw new \DomainException('La date de fin doit etre posterieure a la date de debut.');
        }

        if ($this->availabilityRepository->hasBlockedOverlap($property, $start, $end)) {
            throw new \DomainException('Cette periode est deja bloquee.');
        }

        if ($this->reservationRepository->hasConfirmedOverlap($property, $start, $end)) {
            throw new \DomainException('Cette periode contient deja une reservation confirmee.');
        }

        $block = new PropertyAvailability();
        $block->setProperty($property);
        $block->setDateStart($start);
        $block->setDateEnd($end);
        $block->setAvailableDate($start);
        $block->setIsAvailable(false);
        $block->setReason($reason);

        $this->entityManager->persist($block);
        $this->entityManager->flush();

        return $block;
    }
}
