<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AvailabilityBlock;
use App\Entity\Property;
use App\Enum\BlockReason;
use App\Repository\AvailabilityBlockRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

final class AvailabilityService
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly AvailabilityBlockRepository $availabilityBlockRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly RealtimePublisher $realtimePublisher,
    ) {
    }

    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
    ): bool {
        if ($property->getStatus() !== 'published') {
            return false;
        }

        if (($property->getMaxGuests() ?? 0) < $guests) {
            return false;
        }

        if ($this->reservationRepository->countOverlappingConfirmed($property, $checkin, $checkout) > 0) {
            return false;
        }

        return $this->availabilityBlockRepository->countOverlapping($property, $checkin, $checkout) === 0;
    }

    public function blockPeriod(
        Property $property,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?string $notes = null,
    ): AvailabilityBlock {
        if ($start >= $end) {
            throw new \LogicException('La date de fin doit être postérieure à la date de début.');
        }

        if ($this->reservationRepository->countOverlappingConfirmed($property, $start, $end) > 0) {
            throw new \LogicException('Cette période contient déjà une réservation confirmée.');
        }

        if ($this->availabilityBlockRepository->countOverlapping($property, $start, $end) > 0) {
            throw new \LogicException('Cette période chevauche déjà une indisponibilité.');
        }

        $block = new AvailabilityBlock();
        $block->setProperty($property);
        $block->setStartDate($start);
        $block->setEndDate($end);
        $block->setReason(BlockReason::HOST);
        $block->setNotes($notes !== null && trim($notes) !== '' ? trim($notes) : null);

        $this->entityManager->persist($block);
        $this->entityManager->flush();
        $this->realtimePublisher->publishPropertyAvailabilityChanged($property, [
            'source' => 'host_block',
            'startDate' => $start->format('Y-m-d'),
            'endDate' => $end->format('Y-m-d'),
        ]);

        return $block;
    }

    public function removeBlock(AvailabilityBlock $block): void
    {
        $property = $block->getProperty();
        $this->entityManager->remove($block);
        $this->entityManager->flush();
        $this->realtimePublisher->publishPropertyAvailabilityChanged($property, [
            'source' => 'host_block_deleted',
        ]);
    }

    /**
     * @return array{monthStart: \DateTimeImmutable, previousMonth: string, nextMonth: string, days: list<array{date: \DateTimeImmutable, dayNumber: int, reservations: list<\App\Entity\Reservation>, blocks: list<AvailabilityBlock>, status: string}>, blocks: list<AvailabilityBlock>}
     */
    public function getMonthlyCalendar(Property $property, \DateTimeImmutable $month): array
    {
        $monthStart = $month->modify('first day of this month')->setTime(0, 0);
        $monthEnd = $monthStart->modify('first day of next month');
        $reservations = $this->reservationRepository->findOverlappingConfirmed($property, $monthStart, $monthEnd);
        $blocks = $this->availabilityBlockRepository->findOverlapping($property, $monthStart, $monthEnd);
        $days = [];

        for ($day = $monthStart; $day < $monthEnd; $day = $day->modify('+1 day')) {
            $nextDay = $day->modify('+1 day');
            $dayReservations = array_values(array_filter(
                $reservations,
                static fn ($reservation): bool => $reservation->getCheckinDate() < $nextDay && $reservation->getCheckoutDate() > $day,
            ));
            $dayBlocks = array_values(array_filter(
                $blocks,
                static fn (AvailabilityBlock $block): bool => $block->getStartDate() < $nextDay && $block->getEndDate() > $day,
            ));

            $days[] = [
                'date' => $day,
                'dayNumber' => (int) $day->format('j'),
                'reservations' => $dayReservations,
                'blocks' => $dayBlocks,
                'status' => $dayReservations !== [] ? 'reserved' : ($dayBlocks !== [] ? 'blocked' : 'available'),
            ];
        }

        return [
            'monthStart' => $monthStart,
            'previousMonth' => $monthStart->modify('-1 month')->format('Y-m'),
            'nextMonth' => $monthStart->modify('+1 month')->format('Y-m'),
            'days' => $days,
            'blocks' => $blocks,
        ];
    }
}
