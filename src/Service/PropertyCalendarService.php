<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PropertyCalendarService
{
    public function __construct(
        private PropertyAvailabilityRepository $propertyAvailabilityRepository,
        private ReservationRepository $reservationRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function updatePeriod(
        Property $property,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        bool $isAvailable,
        ?string $blockedReason = null,
        ?int $minimumStay = null,
        ?string $priceOverride = null,
    ): void {
        if ($startDate > $endDate) {
            throw new \DomainException('La date de fin doit être postérieure ou égale à la date de début.');
        }

        if ($minimumStay !== null && $minimumStay < 1) {
            throw new \DomainException('Le séjour minimum doit être supérieur ou égal à 1 nuit.');
        }

        if ($priceOverride !== null && (float) $priceOverride < 0) {
            throw new \DomainException('Le tarif spécifique doit être positif.');
        }

        $cursor = $startDate;
        while ($cursor <= $endDate) {
            $entry = $this->propertyAvailabilityRepository->findOneByPropertyAndDate($property, $cursor);

            if (
                $isAvailable
                && $blockedReason === null
                && $minimumStay === null
                && $priceOverride === null
            ) {
                if ($entry !== null) {
                    $this->entityManager->remove($entry);
                }

                $cursor = $cursor->modify('+1 day');

                continue;
            }

            if ($entry === null) {
                $entry = new PropertyAvailability();
                $entry->setProperty($property);
                $entry->setAvailableDate($cursor);
                $this->entityManager->persist($entry);
            }

            $entry->setIsAvailable($isAvailable);
            $entry->setBlockedReason($isAvailable ? null : $blockedReason);
            $entry->setMinimumStay($minimumStay);
            $entry->setPriceOverride($priceOverride);

            $cursor = $cursor->modify('+1 day');
        }
    }

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function buildMonth(Property $property, \DateTimeImmutable $month): array
    {
        $monthStart = $month->modify('first day of this month')->setTime(0, 0);
        $monthEnd = $monthStart->modify('first day of next month');
        $calendarStart = $monthStart->modify('monday this week');
        $calendarEnd = $monthEnd->modify('sunday this week');

        $availabilityEntries = $this->propertyAvailabilityRepository->findEntriesForMonth($property, $calendarStart, $calendarEnd->modify('+1 day'));
        $confirmedReservations = $this->reservationRepository->findConfirmedForPropertyAndPeriod($property, $calendarStart, $calendarEnd->modify('+1 day'));

        $availabilityByDay = [];
        foreach ($availabilityEntries as $entry) {
            $date = $entry->getAvailableDate();
            if ($date === null) {
                continue;
            }

            $availabilityByDay[$date->format('Y-m-d')] = $entry;
        }

        $bookingsByDay = [];
        foreach ($confirmedReservations as $reservation) {
            $guestProfile = $reservation->getGuest()?->getProfile();
            $label = trim(sprintf(
                '%s %s',
                $guestProfile?->getFirstName() ?? '',
                $guestProfile?->getLastName() ?? '',
            ));

            $cursor = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();
            if ($cursor === null || $checkout === null) {
                continue;
            }

            while ($cursor < $checkout) {
                $bookingsByDay[$cursor->format('Y-m-d')] = [
                    'reservationId' => $reservation->getId(),
                    'guestName' => $label !== '' ? $label : $reservation->getGuest()?->getEmail(),
                    'status' => $reservation->getStatus(),
                ];
                $cursor = $cursor->modify('+1 day');
            }
        }

        $weeks = [];
        $cursor = $calendarStart;
        while ($cursor <= $calendarEnd) {
            $week = [];
            for ($index = 0; $index < 7; ++$index) {
                $key = $cursor->format('Y-m-d');
                /** @var PropertyAvailability|null $entry */
                $entry = $availabilityByDay[$key] ?? null;
                $booking = $bookingsByDay[$key] ?? null;

                $week[] = [
                    'date' => $cursor,
                    'inMonth' => $cursor->format('m') === $monthStart->format('m'),
                    'isBlocked' => $entry !== null && !$entry->isAvailable(),
                    'blockedReason' => $entry?->getBlockedReason(),
                    'minimumStay' => $entry?->getMinimumStay(),
                    'priceOverride' => $entry?->getPriceOverride(),
                    'booking' => $booking,
                ];

                $cursor = $cursor->modify('+1 day');
            }

            $weeks[] = $week;
        }

        return $weeks;
    }
}
