<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Message\ReservationCancelledMessage;
use App\Message\ReservationCreatedMessage;
use App\Message\ReservationDecisionMessage;
use App\Repository\ReservationRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class ReservationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AvailabilityService $availabilityService,
        private readonly ReservationRepository $reservationRepository,
        private readonly MessageBusInterface $bus,
    ) {}

    /**
     * Crée une réservation de façon atomique avec lock pessimiste.
     *
     * @param array{
     *   checkinDate: \DateTimeImmutable,
     *   checkoutDate: \DateTimeImmutable,
     *   guestsCount: int
     * } $data
     *
     * @throws \RuntimeException si les dates sont invalides ou si la plage est indisponible
     */
    public function create(Property $property, User $guest, array $data): Reservation
    {
        $checkin = $data['checkinDate'];
        $checkout = $data['checkoutDate'];
        $guestsCount = (int) $data['guestsCount'];

        $reservation = null;
        $this->entityManager->wrapInTransaction(function () use ($property, $guest, $checkin, $checkout, $guestsCount, &$reservation): void {
            // Lock pessimiste : requête sans jointure pour éviter l'erreur PostgreSQL
            // "FOR UPDATE cannot be applied to the nullable side of an outer join".
            /** @var Property $lockedProperty */
            $lockedProperty = $this->entityManager
                ->createQuery('SELECT p FROM ' . Property::class . ' p WHERE p.id = :id')
                ->setParameter('id', $property->getId())
                ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                ->getSingleResult();

            $reasons = $this->availabilityService->getUnavailabilityReasons(
                $lockedProperty,
                $checkin,
                $checkout,
                $guestsCount,
            );

            if ($reasons !== []) {
                throw new \RuntimeException(implode(' ', $reasons));
            }

            $nights = (int) $checkin->diff($checkout)->days;
            $nightlyRate = (float) $lockedProperty->getPricePerNight();
            $subtotal = $nightlyRate * $nights;
            $cleaningFee = (float) ($lockedProperty->getCleaningFee() ?? 0);
            $serviceFee = round($subtotal * 0.12, 2);
            $totalPrice = round($subtotal + $cleaningFee + $serviceFee, 2);

            $isInstant = $lockedProperty->isInstantBooking();
            $status = $isInstant ? 'confirmed' : 'pending';

            $reservation = new Reservation();
            $reservation->setProperty($lockedProperty);
            $reservation->setGuest($guest);
            $reservation->setCheckinDate($checkin);
            $reservation->setCheckoutDate($checkout);
            $reservation->setGuestsCount($guestsCount);
            $reservation->setStatus($status);
            $reservation->setTotalPrice((string) $totalPrice);
            $reservation->setCleaningFee($cleaningFee > 0 ? (string) $cleaningFee : null);
            $reservation->setServiceFee((string) $serviceFee);
            $reservation->setSecurityDeposit($lockedProperty->getSecurityDeposit());
            $reservation->setCurrency('EUR');

            if (!$isInstant) {
                // Soft-lock TTL : 15 minutes
                $reservation->setExpiresAt(new \DateTimeImmutable('+15 minutes'));
            }

            $history = new ReservationStatusHistory();
            $history->setReservation($reservation);
            $history->setOldStatus(null);
            $history->setNewStatus($status);
            $history->setChangedBy($guest);

            $this->entityManager->persist($reservation);
            $this->entityManager->persist($history);
        });

        // Envoi asynchrone des notifications email une fois la transaction commitée.
        \assert($reservation instanceof Reservation);
        $this->bus->dispatch(new ReservationCreatedMessage((string) $reservation->getId()));

        return $reservation;
    }

    /**
     * Accepte une réservation en attente : passage en `confirmed` + notification email asynchrone.
     *
     * @throws \RuntimeException si la réservation n'est pas dans l'état `pending`
     */
    public function acceptReservation(Reservation $reservation, User $actor): void
    {
        if ($reservation->getStatus() !== 'pending') {
            throw new \RuntimeException('Seules les demandes en attente peuvent être acceptées.');
        }

        $this->entityManager->wrapInTransaction(function () use ($reservation, $actor): void {
            $reservation->setStatus('confirmed');
            $reservation->setExpiresAt(null);

            $history = new ReservationStatusHistory();
            $history->setReservation($reservation);
            $history->setOldStatus('pending');
            $history->setNewStatus('confirmed');
            $history->setChangedBy($actor);

            $this->entityManager->persist($history);
        });

        $this->bus->dispatch(new ReservationDecisionMessage(
            (string) $reservation->getId(),
            'accepted',
            null,
        ));
    }

    /**
     * Refuse une réservation en attente : passage en `cancelled` + motif + notification email asynchrone.
     *
     * @throws \RuntimeException si la réservation n'est pas dans l'état `pending`
     */
    public function rejectReservation(Reservation $reservation, User $actor, string $reason): void
    {
        if ($reservation->getStatus() !== 'pending') {
            throw new \RuntimeException('Seules les demandes en attente peuvent être refusées.');
        }

        $this->entityManager->wrapInTransaction(function () use ($reservation, $actor, $reason): void {
            $reservation->setStatus('cancelled');
            $reservation->setCancellationReason($reason);
            $reservation->setExpiresAt(null);

            $history = new ReservationStatusHistory();
            $history->setReservation($reservation);
            $history->setOldStatus('pending');
            $history->setNewStatus('cancelled');
            $history->setChangedBy($actor);
            $history->setReason($reason);

            $this->entityManager->persist($history);
        });

        $this->bus->dispatch(new ReservationDecisionMessage(
            (string) $reservation->getId(),
            'rejected',
            $reason,
        ));
    }

    /**
     * Annule une réservation (pending ou confirmed) à l'initiative du voyageur ou de l'hôte.
     * Les dates sont immédiatement libérées (statut cancelled).
     * Les deux parties sont notifiées par email de façon asynchrone.
     *
     * @throws \RuntimeException si la réservation ne peut pas être annulée ou si l'acteur n'est pas autorisé
     */
    public function cancelReservation(Reservation $reservation, User $actor, string $reason): void
    {
        $allowedStatuses = ['pending', 'confirmed'];
        if (!in_array($reservation->getStatus(), $allowedStatuses, true)) {
            throw new \RuntimeException('Cette réservation ne peut plus être annulée (statut actuel : ' . $reservation->getStatus() . ').');
        }

        $isGuest = $reservation->getGuest()?->getId() === $actor->getId();
        $isHost = $reservation->getProperty()?->getHost()?->getId() === $actor->getId();

        if (!$isGuest && !$isHost) {
            throw new \RuntimeException('Vous n\'êtes pas autorisé à annuler cette réservation.');
        }

        $cancelledByRole = $isHost ? 'host' : 'guest';
        $oldStatus = $reservation->getStatus();

        $this->entityManager->wrapInTransaction(function () use ($reservation, $actor, $reason, $oldStatus, $cancelledByRole): void {
            $reservation->setStatus('cancelled');
            $reservation->setCancellationReason($reason);
            $reservation->setExpiresAt(null);

            $history = new ReservationStatusHistory();
            $history->setReservation($reservation);
            $history->setOldStatus($oldStatus);
            $history->setNewStatus('cancelled');
            $history->setChangedBy($actor);
            $history->setReason($reason);

            $this->entityManager->persist($history);
        });

        $this->bus->dispatch(new ReservationCancelledMessage(
            (string) $reservation->getId(),
            $cancelledByRole,
            $reason,
        ));
    }
}
