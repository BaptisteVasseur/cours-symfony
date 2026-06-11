<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Message\BookingCancelledMessage;
use App\Message\BookingConfirmedMessage;
use App\Message\BookingPendingMessage;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class ReservationService
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly ReservationRepository $reservationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
    ) {}

    public function createBooking(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guestsCount,
    ): Reservation {
        if (!$this->availabilityService->isAvailable($property, $checkin, $checkout, $guestsCount)) {
            throw new \RuntimeException('Ce logement n\'est pas disponible pour les dates ou le nombre de voyageurs sélectionnés.');
        }

        $nights = (int) $checkin->diff($checkout)->days;
        $nightlyRate = (float) $property->getPricePerNight();
        $subtotal = $nightlyRate * $nights;
        $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
        $serviceFee = round($subtotal * 0.12, 2);
        $totalPrice = round($subtotal + $cleaningFee + $serviceFee, 2);

        $status = $property->isInstantBooking() ? 'confirmed' : 'pending';

        $reservation = new Reservation();
        $reservation->setProperty($property);
        $reservation->setGuest($guest);
        $reservation->setCheckinDate($checkin);
        $reservation->setCheckoutDate($checkout);
        $reservation->setGuestsCount($guestsCount);
        $reservation->setStatus($status);
        $reservation->setTotalPrice((string) $totalPrice);
        $reservation->setCleaningFee($cleaningFee > 0 ? (string) $cleaningFee : null);
        $reservation->setServiceFee((string) $serviceFee);
        $reservation->setSecurityDeposit($property->getSecurityDeposit());
        $reservation->setCurrency('EUR');

        $this->recordStatusChange($reservation, null, $status, $guest);

        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

        if ($status === 'pending') {
            $this->bus->dispatch(new BookingPendingMessage((string) $reservation->getId()));
        } else {
            $this->bus->dispatch(new BookingConfirmedMessage((string) $reservation->getId()));
        }

        return $reservation;
    }

    public function confirm(Reservation $reservation, User $actor): void
    {
        if ($reservation->getStatus() !== 'pending') {
            throw new \RuntimeException('Seule une réservation en attente peut être confirmée.');
        }

        // Re-check availability at confirmation time (race-condition guard)
        if (!$this->availabilityService->isAvailable(
            $reservation->getProperty(),
            $reservation->getCheckinDate(),
            $reservation->getCheckoutDate(),
            $reservation->getGuestsCount(),
        )) {
            throw new \RuntimeException('Ce logement n\'est plus disponible pour ces dates.');
        }

        $old = $reservation->getStatus();
        $reservation->setStatus('confirmed');
        $this->recordStatusChange($reservation, $old, 'confirmed', $actor);

        $this->entityManager->flush();

        $this->bus->dispatch(new BookingConfirmedMessage((string) $reservation->getId()));
    }

    public function cancel(Reservation $reservation, User $actor, string $reason): void
    {
        if ($reservation->getStatus() === 'cancelled' || $reservation->getStatus() === 'completed') {
            throw new \RuntimeException('Cette réservation ne peut pas être annulée.');
        }

        $old = $reservation->getStatus();
        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);
        $this->recordStatusChange($reservation, $old, 'cancelled', $actor);

        $this->entityManager->flush();

        $this->bus->dispatch(new BookingCancelledMessage((string) $reservation->getId(), (string) $actor->getId(), $reason));
    }

    public function complete(Reservation $reservation, User $actor): void
    {
        if ($reservation->getStatus() !== 'confirmed') {
            throw new \RuntimeException('Seule une réservation confirmée peut être marquée comme terminée.');
        }

        $old = $reservation->getStatus();
        $reservation->setStatus('completed');
        $this->recordStatusChange($reservation, $old, 'completed', $actor);

        $this->entityManager->flush();
    }

    public function expire(Reservation $reservation): void
    {
        if ($reservation->getStatus() !== 'pending') {
            return;
        }

        $old = $reservation->getStatus();
        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason('Annulation automatique : délai de réponse de l\'hôte dépassé (24h).');
        $this->recordStatusChange($reservation, $old, 'cancelled', null);

        $this->entityManager->flush();

        $this->bus->dispatch(new BookingCancelledMessage(
            (string) $reservation->getId(),
            'system',
            'Expiration automatique après 24h sans réponse.',
        ));
    }

    private function recordStatusChange(
        Reservation $reservation,
        ?string $old,
        string $new,
        ?User $actor,
    ): void {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($old);
        $history->setNewStatus($new);
        $history->setChangedBy($actor);
        $reservation->addStatusHistory($history);
        $this->entityManager->persist($history);
    }
}
