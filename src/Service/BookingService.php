<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Listing;
use App\Entity\User;
use App\Message\BookingCancelledMessage;
use App\Message\BookingConfirmedMessage;
use App\Message\BookingPendingMessage;
use App\Message\CheckBookingExpiryMessage;
use App\Repository\BlockedPeriodRepository;
use App\Repository\BookingRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class BookingService
{
    public function __construct(
        private readonly EntityManagerInterface  $em,
        private readonly BlockedPeriodRepository $blockedPeriodRepo,
        private readonly BookingRepository       $bookingRepo,
        private readonly MessageBusInterface     $bus,
    ) {}

    /**
     * Vérifie les 4 conditions de disponibilité sans verrou.
     * Utiliser createBooking() pour la vérification transactionnelle avec verrou.
     */
    public function isAvailable(Listing $listing, \DateTimeInterface $checkin, \DateTimeInterface $checkout, int $guestsCount): bool
    {
        if (!$listing->isActive()) {
            return false;
        }

        if ($listing->getMaxGuests() < $guestsCount) {
            return false;
        }

        if ($this->blockedPeriodRepo->findOverlapping($listing, $checkin, $checkout) !== []) {
            return false;
        }

        if ($this->bookingRepo->findConfirmedOverlapping($listing, $checkin, $checkout) !== []) {
            return false;
        }

        return true;
    }

    /**
     * Crée une réservation de manière transactionnelle avec verrou pessimiste.
     * Lance une \RuntimeException si les dates ne sont plus disponibles.
     */
    public function createBooking(Listing $listing, User $guest, \DateTimeInterface $checkin, \DateTimeInterface $checkout, int $guestsCount): Booking
    {
        $booking = $this->em->wrapInTransaction(function () use ($listing, $guest, $checkin, $checkout, $guestsCount): Booking {
            // Verrou pessimiste : bloque toute écriture concurrente sur ce listing
            $this->em->lock($listing, LockMode::PESSIMISTIC_WRITE);

            if (!$this->isAvailable($listing, $checkin, $checkout, $guestsCount)) {
                throw new \RuntimeException('Ces dates ne sont plus disponibles.');
            }

            $nights     = (int) $checkin->diff($checkout)->days;
            $totalPrice = (float) $listing->getPricePerNight() * $nights;

            $booking = new Booking();
            $booking->setListing($listing)
                    ->setGuest($guest)
                    ->setStartDate(\DateTime::createFromInterface($checkin))
                    ->setEndDate(\DateTime::createFromInterface($checkout))
                    ->setGuestsCount($guestsCount)
                    ->setTotalPrice((string) $totalPrice)
                    ->setCurrency('EUR')
                    ->setStatus($listing->isInstantBooking() ? 'confirmed' : 'pending')
                    ->setCreatedAt(new \DateTimeImmutable())
                    ->setUpdatedAt(new \DateTimeImmutable());

            $this->em->persist($booking);

            return $booking;
        });

        // Dispatch après le commit pour garantir que la réservation est bien en base
        if ($booking->getStatus() === 'confirmed') {
            $this->bus->dispatch(new BookingConfirmedMessage($booking->getId()));
        } else {
            $this->bus->dispatch(new BookingPendingMessage($booking->getId()));
            // Expiration automatique après 24h si l'hôte n'a pas traité la demande
            $this->bus->dispatch(
                new CheckBookingExpiryMessage($booking->getId()),
                [new DelayStamp(86400 * 1000)]
            );
        }

        return $booking;
    }

    /**
     * Confirme une réservation pending et annule les autres demandes en conflit.
     */
    public function confirm(Booking $booking): void
    {
        $booking->setStatus('confirmed')
                ->setUpdatedAt(new \DateTimeImmutable());

        // Annule automatiquement les autres demandes pending qui chevauchent ces dates
        $conflicts = $this->bookingRepo->findPendingOverlapping(
            $booking->getListing(),
            $booking->getStartDate(),
            $booking->getEndDate(),
            $booking->getId()
        );
        foreach ($conflicts as $conflict) {
            $conflict->setStatus('cancelled')
                     ->setCancelReason('Autre réservation confirmée pour ces dates.')
                     ->setUpdatedAt(new \DateTimeImmutable());
        }

        $this->em->flush();
        $this->bus->dispatch(new BookingConfirmedMessage($booking->getId()));
    }

    /**
     * Annule une réservation (par l'hôte ou le voyageur) avec un motif obligatoire.
     */
    public function cancel(Booking $booking, string $reason, string $cancelledBy): void
    {
        $booking->setStatus('cancelled')
                ->setCancelReason($reason)
                ->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();
        $this->bus->dispatch(new BookingCancelledMessage($booking->getId(), $reason, $cancelledBy));
    }
}
