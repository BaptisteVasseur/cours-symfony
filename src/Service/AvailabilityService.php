<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Message\BookingBlockedMessage;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class AvailabilityService
{
    public function __construct(
        private readonly EntityManagerInterface         $em,
        private readonly PropertyAvailabilityRepository $availabilityRepository,
        private readonly ReservationRepository          $reservationRepository,
        private readonly BookingService                 $bookingService,
        private readonly MessageBusInterface            $bus,
    ) {}

    /**
     * Block all days from $start to $end (inclusive) for $property.
     * Optionally set a priceOverride and/or minimumStay on each row.
     * Auto-cancels any overlapping PENDING reservations.
     */
    public function blockPeriod(
        Property $property,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?string $blockReason = null,
    ): void {
        if ($end < $start) {
            throw new \InvalidArgumentException('La date de fin doit être postérieure ou égale à la date de début.');
        }

        $current = $start;
        while ($current <= $end) {
            $row = $this->availabilityRepository->findOneByPropertyAndDate($property, $current);
            if ($row === null) {
                $row = new PropertyAvailability();
                $row->setProperty($property);
                $row->setAvailableDate($current);
                $this->em->persist($row);
            }

            $row->setIsAvailable(false);
            $row->setBlockReason($blockReason);

            $current = $current->modify('+1 day');
        }

        // Auto-cancel overlapping PENDING reservations
        $pendingOverlapping = $this->reservationRepository->findPendingOverlapping($property, $start, $end);
        foreach ($pendingOverlapping as $reservation) {
            $guest = $reservation->getGuest();

            $this->bookingService->cancelBySystem(
                $reservation,
                'Dates bloquées par l\'hôte',
            );

            $this->bus->dispatch(new BookingBlockedMessage(
                reservationId:  (string) $reservation->getId(),
                propertyTitle:  $property->getTitle() ?? '',
                guestFirstName: $guest->getProfile()?->getFirstName() ?? $guest->getEmail(),
                guestEmail:     $guest->getEmail(),
                blockedFrom:    $start->format('d/m/Y'),
                blockedTo:      $end->format('d/m/Y'),
            ));
        }

        $this->em->flush();
    }

    /**
     * Set a special price on available days from $start to $end (inclusive).
     * Does NOT block the dates — days remain bookable.
     */
    public function setPricePeriod(
        Property $property,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        float $priceOverride,
    ): void {
        if ($end < $start) {
            throw new \InvalidArgumentException('La date de fin doit être postérieure ou égale à la date de début.');
        }

        $current = $start;
        while ($current <= $end) {
            $row = $this->availabilityRepository->findOneByPropertyAndDate($property, $current);
            if ($row === null) {
                $row = new PropertyAvailability();
                $row->setProperty($property);
                $row->setAvailableDate($current);
                $row->setIsAvailable(true);
                $this->em->persist($row);
            }
            $row->setPriceOverride((string) $priceOverride);
            $current = $current->modify('+1 day');
        }

        $this->em->flush();
    }

    /**
     * Unblock all days from $start to $end (inclusive), making them available again.
     */
    public function unblockPeriod(Property $property, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        $current = $start;
        while ($current <= $end) {
            $row = $this->availabilityRepository->findOneByPropertyAndDate($property, $current);
            if ($row !== null) {
                $row->setIsAvailable(true);
            }
            $current = $current->modify('+1 day');
        }
        $this->em->flush();
    }

    /**
     * Returns blocked dates for a property between two dates, keyed by Y-m-d string.
     * Value is an array with 'blocked' => true and optional 'reason'.
     *
     * @return array<string, array{blocked: bool, reason: string|null}>
     */
    public function getBlockedDateMap(Property $property, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->availabilityRepository->findBlockedRowsByProperty($property, $from, $to);
        $map  = [];
        foreach ($rows as $row) {
            $date = $row->getAvailableDate();
            if ($date !== null) {
                $map[$date->format('Y-m-d')] = [
                    'blocked' => true,
                    'reason'  => $row->getBlockReason(),
                ];
            }
        }

        return $map;
    }
}
