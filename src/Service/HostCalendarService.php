<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\Reservation;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;

/**
 * Construit la vue calendrier mensuelle d'un logement pour l'interface hôte :
 * pour chaque jour, indique s'il est disponible, réservé, en attente ou bloqué.
 */
final class HostCalendarService
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_PENDING = 'pending';
    public const STATUS_BLOCKED = 'blocked';

    private const MONTHS_FR = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];

    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly PropertyAvailabilityRepository $availabilityRepository,
    ) {
    }

    /**
     * @return array{
     *     year: int,
     *     month: int,
     *     label: string,
     *     prev: string,
     *     next: string,
     *     weeks: list<list<array<string, mixed>>>,
     *     blocks: list<PropertyAvailability>
     * }
     */
    public function buildMonth(Property $property, int $year, int $month): array
    {
        $monthFirst = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $monthLast = $monthFirst->modify('last day of this month');

        // La grille commence le lundi de la semaine du 1er et finit le dimanche de la semaine du dernier jour.
        $gridStart = $monthFirst->modify('monday this week');
        $gridEnd = $monthLast->modify('sunday this week');

        $reservations = $this->reservationRepository->findOverlappingForProperty($property, $gridStart, $gridEnd);
        $blocks = $this->availabilityRepository->findBlocksForProperty($property, $gridStart, $gridEnd);

        $today = new \DateTimeImmutable('today');

        $weeks = [];
        $week = [];
        $cursor = $gridStart;

        while ($cursor <= $gridEnd) {
            $week[] = $this->buildDay($cursor, $monthFirst, $today, $reservations, $blocks);

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }

            $cursor = $cursor->modify('+1 day');
        }

        return [
            'year' => $year,
            'month' => $month,
            'label' => $this->formatMonthLabel($monthFirst),
            'prev' => $monthFirst->modify('-1 month')->format('Y-m'),
            'next' => $monthFirst->modify('+1 month')->format('Y-m'),
            'weeks' => $weeks,
            'blocks' => $this->availabilityRepository->findBlocksForProperty($property, $monthFirst, $monthLast),
        ];
    }

    /**
     * @param list<Reservation>          $reservations
     * @param list<PropertyAvailability> $blocks
     *
     * @return array<string, mixed>
     */
    private function buildDay(
        \DateTimeImmutable $date,
        \DateTimeImmutable $monthFirst,
        \DateTimeImmutable $today,
        array $reservations,
        array $blocks,
    ): array {
        $reservation = $this->findReservationForDay($date, $reservations);
        $block = $this->findBlockForDay($date, $blocks);

        if ($reservation !== null) {
            $status = $reservation->getStatus() === 'pending' ? self::STATUS_PENDING : self::STATUS_RESERVED;
        } elseif ($block !== null) {
            $status = self::STATUS_BLOCKED;
        } else {
            $status = self::STATUS_AVAILABLE;
        }

        return [
            'date' => $date,
            'day' => (int) $date->format('j'),
            'inMonth' => $date->format('Y-m') === $monthFirst->format('Y-m'),
            'isPast' => $date < $today,
            'isToday' => $date == $today,
            'status' => $status,
            'reservation' => $reservation,
            'block' => $block,
        ];
    }

    /**
     * Réservation occupant la nuit du jour donné : checkinDate <= $date < checkoutDate.
     * Une réservation confirmée prime sur une réservation en attente.
     *
     * @param list<Reservation> $reservations
     */
    private function findReservationForDay(\DateTimeImmutable $date, array $reservations): ?Reservation
    {
        $pending = null;

        foreach ($reservations as $reservation) {
            if ($reservation->getCheckinDate() <= $date && $date < $reservation->getCheckoutDate()) {
                if ($reservation->getStatus() !== 'pending') {
                    return $reservation;
                }

                $pending ??= $reservation;
            }
        }

        return $pending;
    }

    /**
     * Blocage couvrant le jour donné : startDate <= $date <= endDate (bornes incluses).
     *
     * @param list<PropertyAvailability> $blocks
     */
    private function findBlockForDay(\DateTimeImmutable $date, array $blocks): ?PropertyAvailability
    {
        foreach ($blocks as $block) {
            if ($block->getStartDate() <= $date && $date <= $block->getEndDate()) {
                return $block;
            }
        }

        return null;
    }

    private function formatMonthLabel(\DateTimeImmutable $date): string
    {
        return self::MONTHS_FR[(int) $date->format('n')] . ' ' . $date->format('Y');
    }
}
