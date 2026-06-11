<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Entity\BookingHistory;
use App\Entity\Listing;
use App\Entity\User;
use App\Enum\BookingStatus;
use App\Exception\UnavailableDatesException;
use App\Message\BookingCancelledMessage;
use App\Message\BookingConfirmedMessage;
use App\Message\BookingCreatedMessage;
use App\Message\BookingRejectedMessage;
use App\Repository\ListingRepository;
use App\ValueObject\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface as MessengerException;
use Symfony\Component\Messenger\MessageBusInterface;

final class BookingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ListingRepository $listingRepository,
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
        private readonly BookingStateMachineService $stateMachine,
        private readonly NotificationService $notifications,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function createBooking(Listing $listing, User $guest, DateRange $range, int $guests): Booking
    {
        $guests = max(1, $guests);

        $booking = $this->em->wrapInTransaction(function () use ($listing, $guest, $range, $guests): Booking {
            $lockedListing = $this->listingRepository->findForUpdate((string) $listing->getId());
            if (!$lockedListing instanceof Listing) {
                throw new UnavailableDatesException('Logement introuvable.');
            }

            if (!$this->availability->isAvailable($lockedListing, $range, $guests)) {
                throw new UnavailableDatesException();
            }

            $quote = $this->pricing->quote($lockedListing, $range);
            $instant = $lockedListing->isInstantBooking();
            $status = $instant ? BookingStatus::Confirmed : BookingStatus::Pending;

            $booking = (new Booking())
                ->setListing($lockedListing)
                ->setGuest($guest)
                ->setCheckIn($range->checkIn)
                ->setCheckOut($range->checkOut)
                ->setGuestsCount($guests)
                ->setNightsCount($quote->nights)
                ->setBaseAmount($quote->baseAmount)
                ->setCleaningFee($quote->cleaningFee)
                ->setServiceFee($quote->serviceFee)
                ->setTaxesAmount($quote->taxesAmount)
                ->setTotalAmount($quote->totalAmount)
                ->setCurrency($quote->currency)
                ->setBookingStatus($status)
                ->setCreatedAt(new \DateTimeImmutable());

            if ($instant) {
                $booking->setConfirmedAt(new \DateTimeImmutable());
            }

            $this->recordHistory($booking, $status, $guest, $instant
                ? 'Réservation instantanée confirmée.'
                : 'Demande de réservation envoyée.');

            $this->em->persist($booking);

            if ($instant) {
                $this->notifications->push(
                    $guest,
                    'booking_confirmed',
                    'Réservation confirmée',
                    sprintf('Votre séjour à « %s » est confirmé.', $lockedListing->getTitle())
                );
                $this->notifications->push(
                    $lockedListing->getHost(),
                    'booking_confirmed',
                    'Nouvelle réservation',
                    sprintf('Réservation instantanée confirmée pour « %s ».', $lockedListing->getTitle())
                );
            } else {
                $this->notifications->push(
                    $lockedListing->getHost(),
                    'booking_request',
                    'Nouvelle demande',
                    sprintf('Une demande est en attente pour « %s ».', $lockedListing->getTitle())
                );
            }

            return $booking;
        });

        if ($booking->getBookingStatus() === BookingStatus::Confirmed) {
            $this->dispatch(new BookingConfirmedMessage((string) $booking->getId()));
        } else {
            $this->dispatch(new BookingCreatedMessage((string) $booking->getId()));
        }

        return $booking;
    }


    public function confirm(Booking $booking, User $actor): Booking
    {
        $this->em->wrapInTransaction(function () use ($booking, $actor): void {
            $listing = $booking->getListing();
            $this->listingRepository->findForUpdate((string) $listing->getId());

            $range = new DateRange($booking->getCheckIn(), $booking->getCheckOut());
            if (!$this->availability->isAvailable($listing, $range, $booking->getGuestsCount() ?? 1, $booking)) {
                throw new UnavailableDatesException('Impossible de confirmer : ces dates ont été prises entre-temps.');
            }

            $this->stateMachine->apply($booking, BookingStatus::Confirmed);
            $booking->setConfirmedAt(new \DateTimeImmutable());
            $this->recordHistory($booking, BookingStatus::Confirmed, $actor, 'Demande acceptée par l\'hôte.');

            $this->notifications->push(
                $booking->getGuest(),
                'booking_confirmed',
                'Réservation acceptée',
                sprintf('Votre demande pour « %s » a été acceptée.', $listing->getTitle())
            );
        });

        $this->dispatch(new BookingConfirmedMessage((string) $booking->getId()));

        return $booking;
    }

    public function reject(Booking $booking, User $actor, string $reason): Booking
    {
        $this->em->wrapInTransaction(function () use ($booking, $actor, $reason): void {
            $this->stateMachine->apply($booking, BookingStatus::Cancelled);
            $booking->setCancelledAt(new \DateTimeImmutable());
            $booking->setCancelledBy($actor);
            $booking->setCancellationReason($reason);
            $this->recordHistory($booking, BookingStatus::Cancelled, $actor, 'Demande refusée : ' . $reason);

            $this->notifications->push(
                $booking->getGuest(),
                'booking_rejected',
                'Demande refusée',
                sprintf('Votre demande pour « %s » a été refusée.', $booking->getListing()->getTitle())
            );
        });

        $this->dispatch(new BookingRejectedMessage((string) $booking->getId()));

        return $booking;
    }

    public function cancel(Booking $booking, User $actor, string $reason): Booking
    {
        $this->em->wrapInTransaction(function () use ($booking, $actor, $reason): void {
            $this->stateMachine->apply($booking, BookingStatus::Cancelled);
            $booking->setCancelledAt(new \DateTimeImmutable());
            $booking->setCancelledBy($actor);
            $booking->setCancellationReason($reason);
            $this->recordHistory($booking, BookingStatus::Cancelled, $actor, 'Annulation : ' . $reason);

            $this->notifications->push(
                $booking->getGuest(),
                'booking_cancelled',
                'Réservation annulée',
                sprintf('La réservation « %s » a été annulée.', $booking->getListing()->getTitle())
            );
            $this->notifications->push(
                $booking->getListing()->getHost(),
                'booking_cancelled',
                'Réservation annulée',
                sprintf('La réservation « %s » a été annulée.', $booking->getListing()->getTitle())
            );
        });

        $this->dispatch(new BookingCancelledMessage((string) $booking->getId()));

        return $booking;
    }

    public function expireAsSystem(Booking $booking): Booking
    {
        $reason = 'Expiration automatique : demande sans réponse de l\'hôte sous 24h.';

        $this->em->wrapInTransaction(function () use ($booking, $reason): void {
            $this->stateMachine->apply($booking, BookingStatus::Cancelled);
            $booking->setCancelledAt(new \DateTimeImmutable());
            $booking->setCancellationReason($reason);
            $this->recordHistory($booking, BookingStatus::Cancelled, null, $reason);

            $this->notifications->push(
                $booking->getGuest(),
                'booking_cancelled',
                'Demande expirée',
                sprintf('Votre demande pour « %s » a expiré faute de réponse.', $booking->getListing()->getTitle())
            );
        });

        $this->dispatch(new BookingCancelledMessage((string) $booking->getId()));

        return $booking;
    }

    private function recordHistory(Booking $booking, BookingStatus $status, ?User $author, ?string $comment): void
    {
        $history = (new BookingHistory())
            ->setStatus($status)
            ->setAuthor($author)
            ->setComment($comment);

        $booking->addHistory($history);
        $this->em->persist($history);
    }

    private function dispatch(object $message): void
    {
        try {
            $this->bus->dispatch($message);
        } catch (MessengerException $e) {
            $this->logger->error('Échec du dispatch d\'une notification de réservation : ' . $e->getMessage(), [
                'message' => $message::class,
                'exception' => $e,
            ]);
        }
    }
}
