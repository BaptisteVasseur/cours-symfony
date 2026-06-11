<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Message\ReservationEmailNotificationMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ReservationService
{
    public function __construct(
        private AvailabilityService $availabilityService,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function createReservation(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guestsCount,
    ): Reservation {
        if ($property->getHost()?->getId() === $guest->getId()) {
            throw new \DomainException('Vous ne pouvez pas reserver votre propre logement.');
        }

        $reservation = $this->transactional(function () use ($property, $guest, $checkin, $checkout, $guestsCount): Reservation {
            if (!$this->availabilityService->isAvailable($property, $checkin, $checkout, $guestsCount)) {
                throw new \DomainException('Ce logement n\'est plus disponible pour ces dates.');
            }

            $reservation = new Reservation();
            $reservation->setProperty($property);
            $reservation->setGuest($guest);
            $reservation->setCheckinDate($checkin);
            $reservation->setCheckoutDate($checkout);
            $reservation->setGuestsCount($guestsCount);
            $reservation->setStatus($property->isInstantBooking() ? Reservation::STATUS_CONFIRMED : Reservation::STATUS_PENDING);
            $this->applyPrices($reservation, $property, $checkin, $checkout);

            $this->entityManager->persist($reservation);
            $this->addStatusHistory($reservation, null, $reservation->getStatus() ?? Reservation::STATUS_PENDING, $guest);

            return $reservation;
        });

        $this->dispatchNotification($reservation, $reservation->getStatus() === Reservation::STATUS_PENDING ? 'pending' : 'confirmed');

        return $reservation;
    }

    public function accept(Reservation $reservation, User $host): void
    {
        $this->transactional(function () use ($reservation, $host): void {
            if ($reservation->getStatus() !== Reservation::STATUS_PENDING) {
                throw new \DomainException('Seules les demandes en attente peuvent etre acceptees.');
            }

            $property = $reservation->getProperty();
            $checkin = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();
            $guestsCount = $reservation->getGuestsCount();

            if ($property === null || $checkin === null || $checkout === null || $guestsCount === null) {
                throw new \DomainException('La reservation est incomplete.');
            }

            if (!$this->availabilityService->isAvailable($property, $checkin, $checkout, $guestsCount, $reservation)) {
                throw new \DomainException('Ces dates ne sont plus disponibles.');
            }

            $reservation->setStatus(Reservation::STATUS_CONFIRMED);
            $this->addStatusHistory($reservation, Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED, $host);
        });

        $this->dispatchNotification($reservation, 'confirmed');
    }

    public function decline(Reservation $reservation, User $host, string $reason): void
    {
        $this->cancelWithStatus($reservation, $host, $reason, 'declined');
    }

    public function cancel(Reservation $reservation, User $user, string $reason): void
    {
        $this->cancelWithStatus($reservation, $user, $reason, 'cancelled');
    }

    private function cancelWithStatus(Reservation $reservation, User $user, string $reason, string $notificationType): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \DomainException('Le motif est obligatoire.');
        }

        $this->transactional(function () use ($reservation, $user, $reason): void {
            if (in_array($reservation->getStatus(), [Reservation::STATUS_CANCELLED, Reservation::STATUS_COMPLETED], true)) {
                throw new \DomainException('Cette reservation ne peut plus etre annulee.');
            }

            $oldStatus = $reservation->getStatus();
            $reservation->setStatus(Reservation::STATUS_CANCELLED);
            $reservation->setCancellationReason($reason);
            $this->addStatusHistory($reservation, $oldStatus, Reservation::STATUS_CANCELLED, $user);
        });

        $this->dispatchNotification($reservation, $notificationType, $reason);
    }

    private function applyPrices(Reservation $reservation, Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): void
    {
        $nights = max(1, (int) $checkin->diff($checkout)->days);
        $nightlyRate = (float) $property->getPricePerNight();
        $subtotal = $nightlyRate * $nights;
        $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
        $serviceFee = round($subtotal * 0.12, 2);
        $totalPrice = round($subtotal + $cleaningFee + $serviceFee, 2);

        $reservation->setTotalPrice(number_format($totalPrice, 2, '.', ''));
        $reservation->setCleaningFee($cleaningFee > 0 ? number_format($cleaningFee, 2, '.', '') : null);
        $reservation->setServiceFee(number_format($serviceFee, 2, '.', ''));
        $reservation->setSecurityDeposit($property->getSecurityDeposit());
        $reservation->setCurrency('EUR');
    }

    private function addStatusHistory(Reservation $reservation, ?string $oldStatus, string $newStatus, User $changedBy): void
    {
        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus);
        $history->setNewStatus($newStatus);
        $history->setChangedBy($changedBy);

        $this->entityManager->persist($history);
    }

    private function transactional(callable $callback): mixed
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $result = $callback();
            $this->entityManager->flush();
            $connection->commit();

            return $result;
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }

    private function dispatchNotification(Reservation $reservation, string $type, ?string $reason = null): void
    {
        $id = $reservation->getId();
        if ($id === null) {
            return;
        }

        $this->messageBus->dispatch(new ReservationEmailNotificationMessage($id->toRfc4122(), $type, $reason));
    }
}
