<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Message\BookingCancelledNotification;
use App\Message\BookingConfirmedNotification;
use App\Message\BookingRejectedNotification;
use App\Message\BookingRequestedNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class BookingManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        private readonly AvailabilityChecker $availabilityChecker,
        private readonly PriceCalculator $priceCalculator,
    ) {
    }

    public function create(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
    ): Reservation {
        if (!$this->availabilityChecker->isAvailable($property, $checkin, $checkout, $guests)) {
            throw new BookingException('Les dates demandées ne sont pas disponibles.');
        }

        $price = $this->priceCalculator->compute($property, $checkin, $checkout);
        $status = $property->isInstantBooking() ? 'confirmed' : 'pending';

        $reservation = new Reservation();
        $reservation->setProperty($property);
        $reservation->setGuest($guest);
        $reservation->setCheckinDate($checkin->setTime(0, 0));
        $reservation->setCheckoutDate($checkout->setTime(0, 0));
        $reservation->setGuestsCount($guests);
        $reservation->setStatus($status);
        $reservation->setTotalPrice(number_format($price['total'], 2, '.', ''));
        $reservation->setCleaningFee(number_format($price['cleaningFee'], 2, '.', ''));
        $reservation->setServiceFee(number_format($price['serviceFee'], 2, '.', ''));
        $reservation->setSecurityDeposit($property->getSecurityDeposit());
        $reservation->setCurrency('EUR');

        $this->em->persist($reservation);
        $this->recordHistory($reservation, null, $status, $guest);
        $this->em->flush();

        if ($status === 'confirmed') {
            $this->bus->dispatch(new BookingConfirmedNotification((string) $reservation->getId()));
        } else {
            $this->bus->dispatch(new BookingRequestedNotification((string) $reservation->getId()));
        }

        return $reservation;
    }

    public function confirm(Reservation $reservation, User $actor): void
    {
        $this->assertStatus($reservation, ['pending']);

        if (!$this->availabilityChecker->isAvailable(
            $reservation->getProperty(),
            $reservation->getCheckinDate(),
            $reservation->getCheckoutDate(),
            $reservation->getGuestsCount(),
            $reservation,
        )) {
            throw new BookingException('Les dates ne sont plus disponibles, impossible de confirmer.');
        }

        $this->transition($reservation, 'confirmed', $actor);
        $this->bus->dispatch(new BookingConfirmedNotification((string) $reservation->getId()));
    }

    public function reject(Reservation $reservation, User $actor, string $reason): void
    {
        $this->assertStatus($reservation, ['pending']);
        $this->requireReason($reason);

        $reservation->setCancellationReason($reason);
        $this->transition($reservation, 'cancelled', $actor);
        $this->bus->dispatch(new BookingRejectedNotification((string) $reservation->getId()));
    }

    public function cancel(Reservation $reservation, User $actor, string $reason): void
    {
        $this->assertStatus($reservation, ['pending', 'confirmed']);
        $this->requireReason($reason);

        $reservation->setCancellationReason($reason);
        $this->transition($reservation, 'cancelled', $actor);
        $this->bus->dispatch(new BookingCancelledNotification((string) $reservation->getId()));
    }

    private function transition(Reservation $reservation, string $newStatus, User $actor): void
    {
        $oldStatus = $reservation->getStatus();
        $reservation->setStatus($newStatus);
        $this->recordHistory($reservation, $oldStatus, $newStatus, $actor);
        $this->em->flush();
    }

    private function recordHistory(Reservation $reservation, ?string $oldStatus, string $newStatus, User $actor): void
    {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus);
        $history->setNewStatus($newStatus);
        $history->setChangedBy($actor);
        $reservation->addStatusHistory($history);
        $this->em->persist($history);
    }

    /**
     * @param list<string> $allowed
     */
    private function assertStatus(Reservation $reservation, array $allowed): void
    {
        if (!in_array($reservation->getStatus(), $allowed, true)) {
            throw new BookingException(sprintf(
                'Transition impossible depuis le statut "%s".',
                (string) $reservation->getStatus(),
            ));
        }
    }

    private function requireReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw new BookingException('Un motif est obligatoire.');
        }
    }
}
