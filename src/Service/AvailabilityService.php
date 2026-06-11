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

    /**
     * @return list<array{label: string, days: list<array{date: \DateTimeImmutable, currentMonth: bool, unavailable: bool, reason: ?string}>}>
     */
    public function buildBookingCalendar(Property $property, int $months = 3): array
    {
        $today = (new \DateTimeImmutable('today'))->setTime(0, 0);
        $firstMonth = $today->modify('first day of this month');
        $lastMonthEnd = $firstMonth->modify(sprintf('+%d months', $months))->modify('first day of this month');
        $gridStart = $firstMonth->modify('monday this week');
        $gridEnd = $lastMonthEnd->modify('sunday this week')->modify('+1 day');
        $unavailableDates = $this->getUnavailableDates($property, $gridStart, $gridEnd);
        $calendar = [];

        for ($index = 0; $index < $months; $index++) {
            $monthStart = $firstMonth->modify(sprintf('+%d months', $index));
            $monthEnd = $monthStart->modify('first day of next month');
            $monthGridStart = $monthStart->modify('monday this week');
            $monthGridEnd = $monthEnd->modify('sunday this week')->modify('+1 day');
            $days = [];

            foreach (new \DatePeriod($monthGridStart, new \DateInterval('P1D'), $monthGridEnd) as $date) {
                $day = \DateTimeImmutable::createFromInterface($date);
                $key = $day->format('Y-m-d');
                $isPast = $day < $today;

                $days[] = [
                    'date' => $day,
                    'currentMonth' => $day->format('Y-m') === $monthStart->format('Y-m'),
                    'unavailable' => $isPast || isset($unavailableDates[$key]),
                    'reason' => $isPast ? 'Passe' : ($unavailableDates[$key] ?? null),
                ];
            }

            $calendar[] = [
                'label' => $monthStart->format('m/Y'),
                'days' => $days,
            ];
        }

        return $calendar;
    }

    /**
     * @return array<string, string>
     */
    private function getUnavailableDates(Property $property, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $dates = [];

        foreach ($this->availabilityRepository->findBlockedForPeriod($property, $start, $end) as $block) {
            $this->markPeriod($dates, $block->getDateStart(), $block->getDateEnd(), $block->getReason() ?: 'Indisponible');
        }

        foreach ($this->reservationRepository->findConfirmedForPeriod($property, $start, $end) as $reservation) {
            $this->markPeriod($dates, $reservation->getCheckinDate(), $reservation->getCheckoutDate(), 'Deja reserve');
        }

        return $dates;
    }

    /**
     * @param array<string, string> $dates
     */
    private function markPeriod(array &$dates, ?\DateTimeImmutable $start, ?\DateTimeImmutable $end, string $reason): void
    {
        if ($start === null || $end === null || $start >= $end) {
            return;
        }

        foreach (new \DatePeriod($start, new \DateInterval('P1D'), $end) as $date) {
            $dates[\DateTimeImmutable::createFromInterface($date)->format('Y-m-d')] = $reason;
        }
    }
}
