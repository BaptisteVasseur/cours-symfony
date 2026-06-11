<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\Reservation;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

final class AvailabilityService
{
    public function __construct(
        private readonly PropertyAvailabilityRepository $availabilityRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
    ): bool {
        return $this->getUnavailabilityReason($property, $checkin, $checkout, $guests) === null;
    }

    public function getUnavailabilityReason(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
    ): ?string {
        if ($checkin >= $checkout) {
            return 'La date de départ doit être postérieure à la date d\'arrivée.';
        }

        if ($property->getStatus() !== 'published') {
            return 'Ce logement n\'est pas publié.';
        }

        if ($guests > $property->getMaxGuests()) {
            return sprintf('Ce logement accepte au maximum %d voyageurs.', $property->getMaxGuests());
        }

        $blockedDays = $this->availabilityRepository->countBlockedDaysInRange($property, $checkin, $checkout);
        if ($blockedDays > 0) {
            return 'Certaines dates de la période demandée sont indisponibles.';
        }

        if ($this->reservationRepository->existsConfirmedOverlap($property, $checkin, $checkout)) {
            return 'Une réservation confirmée chevauche déjà ces dates.';
        }

        $nights = (int) $checkin->diff($checkout)->days;
        $minimumStay = $this->availabilityRepository->getMinimumStayForCheckin($property, $checkin);
        if ($minimumStay !== null && $nights < $minimumStay) {
            return sprintf('La durée minimale de séjour est de %d nuit(s).', $minimumStay);
        }

        return null;
    }

    public function blockDates(
        Property $property,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        ?string $reason = null,
    ): int {
        if ($startDate >= $endDate) {
            throw new \InvalidArgumentException('La date de fin doit être postérieure à la date de début.');
        }

        $blocked = 0;
        $current = $startDate;

        while ($current < $endDate) {
            $existing = $this->availabilityRepository->findOneByPropertyAndDate($property, $current);

            if ($existing !== null) {
                $existing->setIsAvailable(false);
                $existing->setSource('manual');
                $existing->setReason($reason);
            } else {
                $availability = new PropertyAvailability();
                $availability->setProperty($property);
                $availability->setAvailableDate($current);
                $availability->setIsAvailable(false);
                $availability->setSource('manual');
                $availability->setReason($reason);
                $this->entityManager->persist($availability);
            }

            ++$blocked;
            $current = $current->modify('+1 day');
        }

        $this->entityManager->flush();

        return $blocked;
    }

    public function configureDates(
        Property $property,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        ?string $priceOverride = null,
        ?int $minimumStay = null,
    ): int {
        if ($startDate >= $endDate) {
            throw new \InvalidArgumentException('La date de fin doit être postérieure à la date de début.');
        }

        if ($priceOverride === null && $minimumStay === null) {
            throw new \InvalidArgumentException('Indiquez au moins un tarif ou une durée minimale.');
        }

        $configured = 0;
        $current = $startDate;

        while ($current < $endDate) {
            $existing = $this->availabilityRepository->findOneByPropertyAndDate($property, $current);

            if ($existing !== null && !$existing->isAvailable() && $existing->getSource() !== 'manual') {
                $current = $current->modify('+1 day');
                continue;
            }

            if ($existing !== null) {
                $availability = $existing;
            } else {
                $availability = new PropertyAvailability();
                $availability->setProperty($property);
                $availability->setAvailableDate($current);
                $availability->setIsAvailable(true);
                $availability->setSource('manual');
                $this->entityManager->persist($availability);
            }

            if ($priceOverride !== null) {
                $availability->setPriceOverride($priceOverride);
            }

            if ($minimumStay !== null) {
                $availability->setMinimumStay($minimumStay);
            }

            ++$configured;
            $current = $current->modify('+1 day');
        }

        $this->entityManager->flush();

        return $configured;
    }

    public function unblockDates(
        Property $property,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
    ): int {
        if ($startDate >= $endDate) {
            throw new \InvalidArgumentException('La date de fin doit être postérieure à la date de début.');
        }

        $availabilities = $this->availabilityRepository->findBlockedInRange($property, $startDate, $endDate);
        $unblocked = 0;

        foreach ($availabilities as $availability) {
            if ($availability->getSource() !== 'manual') {
                continue;
            }

            $this->entityManager->remove($availability);
            ++$unblocked;
        }

        $this->entityManager->flush();

        return $unblocked;
    }

    public function existsConfirmedOverlap(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        ?Reservation $exclude = null,
    ): bool {
        return $this->reservationRepository->existsConfirmedOverlap($property, $checkin, $checkout, $exclude);
    }
}
