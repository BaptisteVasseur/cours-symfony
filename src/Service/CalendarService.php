<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Listing;
use App\Repository\AvailabilityBlockRepository;
use App\Repository\BookingRepository;

final class CalendarService
{
    private const STATUS_AVAILABLE = 'available';
    private const STATUS_BOOKED = 'booked';
    private const STATUS_BLOCKED = 'blocked';

    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly AvailabilityBlockRepository $blockRepository,
    ) {
    }

    public function buildMonth(Listing $listing, int $year, int $month): array
    {
        $first = (new \DateTimeImmutable())->setDate($year, $month, 1)->setTime(0, 0);
        $today = (new \DateTimeImmutable())->setTime(0, 0);

        $gridStart = $this->mondayOf($first);
        $gridEnd = $gridStart->modify('+42 days');

        $bookings = $this->bookingRepository->findConfirmedInWindow($listing, $gridStart, $gridEnd);
        $blocks = $this->blockRepository->findInWindow($listing, $gridStart, $gridEnd);

        $bookedIntervals = array_map(
            static fn($b) => [$b->getCheckIn(), $b->getCheckOut()],
            $bookings,
        );
        $blockedIntervals = array_map(
            static fn($b) => [$b->getStartDate(), $b->getEndDate()],
            $blocks,
        );

        $weeks = [];
        $cursor = $gridStart;
        for ($w = 0; $w < 6; ++$w) {
            $week = [];
            for ($d = 0; $d < 7; ++$d) {
                $status = self::STATUS_AVAILABLE;
                if ($this->dayInIntervals($cursor, $blockedIntervals)) {
                    $status = self::STATUS_BLOCKED;
                } elseif ($this->dayInIntervals($cursor, $bookedIntervals)) {
                    $status = self::STATUS_BOOKED;
                }

                $week[] = [
                    'date' => $cursor,
                    'inMonth' => ((int) $cursor->format('n')) === $month,
                    'isPast' => $cursor < $today,
                    'status' => $status,
                ];
                $cursor = $cursor->modify('+1 day');
            }
            $weeks[] = $week;
        }

        return [
            'year' => $year,
            'month' => $month,
            'label' => $this->frenchMonthLabel($first),
            'prev' => $this->shiftMonth($year, $month, -1),
            'next' => $this->shiftMonth($year, $month, 1),
            'weeks' => $weeks,
        ];
    }

    private function dayInIntervals(\DateTimeImmutable $day, array $intervals): bool
    {
        foreach ($intervals as [$start, $end]) {
            if ($day >= $start && $day < $end) {
                return true;
            }
        }

        return false;
    }

    private function mondayOf(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $dow = (int) $date->format('N');

        return $date->modify('-' . ($dow - 1) . ' days');
    }

    private function shiftMonth(int $year, int $month, int $delta): array
    {
        $d = (new \DateTimeImmutable())->setDate($year, $month, 1)->modify(($delta > 0 ? '+' : '-') . abs($delta) . ' month');

        return ['year' => (int) $d->format('Y'), 'month' => (int) $d->format('n')];
    }

    private function frenchMonthLabel(\DateTimeImmutable $date): string
    {
        $months = [
            1 => 'Janvier',
            'Février',
            'Mars',
            'Avril',
            'Mai',
            'Juin',
            'Juillet',
            'Août',
            'Septembre',
            'Octobre',
            'Novembre',
            'Décembre'
        ];

        return $months[(int) $date->format('n')] . ' ' . $date->format('Y');
    }
}
