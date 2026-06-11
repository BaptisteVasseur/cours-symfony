<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Property;
use App\Entity\User;
use App\Enum\BookingStatus;
use App\Message\BookingCancelledMessage;
use App\Message\BookingConfirmedMessage;
use App\Message\BookingRequestedMessage;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly BookingRepository $bookingRepo,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {}

    /**
     * Creates a new booking.
     * Status is CONFIRMED if instantBooking, PENDING otherwise.
     * Dispatches async notifications after DB flush.
     *
     * @throws \RuntimeException if dates are unavailable
     */
    public function create(
        Property $property,
        User $traveler,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
        int $guestsCount,
    ): Booking {
        if (!$this->availabilityService->isAvailable($property, $checkIn, $checkOut, $guestsCount)) {
            throw new \RuntimeException('Ces dates ne sont pas disponibles pour ce logement.');
        }

        $nights = $checkIn->diff($checkOut)->days;
        $totalPrice = (string) ($nights * (float) $property->getPricePerNight());

        $status = $property->isInstantBooking() ? BookingStatus::CONFIRMED : BookingStatus::PENDING;

        $booking = new Booking();
        $booking->setProperty($property)
            ->setTraveler($traveler)
            ->setCheckIn($checkIn)
            ->setCheckOut($checkOut)
            ->setGuestsCount($guestsCount)
            ->setTotalPrice($totalPrice)
            ->setStatus($status);

        $this->em->persist($booking);
        $this->em->flush();

        // Dispatch AFTER flush so booking exists in DB when handler runs
        if ($status === BookingStatus::CONFIRMED) {
            $this->bus->dispatch(new BookingConfirmedMessage((string) $booking->getId()));
        } else {
            $this->bus->dispatch(new BookingRequestedMessage((string) $booking->getId()));
        }

        return $booking;
    }

    /**
     * Host confirms a PENDING booking.
     * Re-checks availability to prevent overbooking race condition.
     *
     * @throws \RuntimeException if not PENDING, host mismatch, or dates now taken
     */
    public function confirm(Booking $booking, User $host): void
    {
        if ($booking->getStatus() !== BookingStatus::PENDING) {
            throw new \RuntimeException('Seules les réservations en attente peuvent être confirmées.');
        }

        if ($booking->getProperty()->getHost() !== $host) {
            throw new \RuntimeException('Vous n\'êtes pas l\'hôte de ce logement.');
        }

        // Re-check availability: another booking may have been confirmed in between
        if ($this->bookingRepo->hasConfirmedConflict(
            $booking->getCheckIn(),
            $booking->getCheckOut(),
            $booking->getProperty()
        )) {
            throw new \RuntimeException('Ces dates ont été confirmées pour une autre réservation entre-temps. Veuillez annuler cette demande.');
        }

        $booking->setStatus(BookingStatus::CONFIRMED);
        $this->em->flush();

        $this->bus->dispatch(new BookingConfirmedMessage((string) $booking->getId()));
    }

    /**
     * Cancels a booking (traveler or host).
     *
     * @throws \RuntimeException if booking is already cancelled/completed
     */
    public function cancel(Booking $booking, User $actor, string $reason): void
    {
        if (in_array($booking->getStatus(), [BookingStatus::CANCELLED, BookingStatus::COMPLETED])) {
            throw new \RuntimeException('Cette réservation ne peut pas être annulée.');
        }

        $booking->setStatus(BookingStatus::CANCELLED);
        $booking->setCancellationReason($reason);
        $this->em->flush();

        $this->bus->dispatch(new BookingCancelledMessage((string) $booking->getId()));
    }
}
