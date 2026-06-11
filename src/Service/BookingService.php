<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Enum\ReservationNotificationType;
use App\Exception\ReservationWorkflowException;
use App\Message\SendReservationNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class BookingService
{
    private const int PENDING_EXPIRATION_DAYS = 7;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AvailabilityService $availabilityService,
        private readonly PricingService $pricingService,
        private readonly ReservationWorkflowService $workflowService,
        private readonly MessageBusInterface $messageBus,
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
            throw new ReservationWorkflowException('Vous ne pouvez pas réserver votre propre logement.');
        }

        $unavailabilityReason = $this->availabilityService->getUnavailabilityReason(
            $property,
            $checkin,
            $checkout,
            $guestsCount,
        );

        if ($unavailabilityReason !== null) {
            throw new ReservationWorkflowException($unavailabilityReason);
        }

        $pricing = $this->pricingService->calculate($property, $checkin, $checkout);
        $isInstant = $property->isInstantBooking();
        $initialStatus = $isInstant ? 'confirmed' : 'pending';

        $reservation = new Reservation();
        $reservation->setProperty($property);
        $reservation->setGuest($guest);
        $reservation->setCheckinDate($checkin);
        $reservation->setCheckoutDate($checkout);
        $reservation->setGuestsCount($guestsCount);
        $reservation->setStatus($initialStatus);
        $reservation->setTotalPrice((string) $pricing['totalPrice']);
        $reservation->setCleaningFee($pricing['cleaningFee'] > 0 ? (string) $pricing['cleaningFee'] : null);
        $reservation->setServiceFee((string) $pricing['serviceFee']);
        $reservation->setSecurityDeposit($property->getSecurityDeposit());
        $reservation->setCurrency('EUR');

        if (!$isInstant) {
            $reservation->setExpiresAt(new \DateTimeImmutable('+' . self::PENDING_EXPIRATION_DAYS . ' days'));
        }

        $this->entityManager->persist($reservation);
        $this->workflowService->recordInitialStatus($reservation, $guest);
        $this->entityManager->flush();

        if ($isInstant) {
            $this->messageBus->dispatch(new SendReservationNotification(
                (string) $reservation->getId(),
                ReservationNotificationType::ConfirmedToGuest,
            ));
            $this->messageBus->dispatch(new SendReservationNotification(
                (string) $reservation->getId(),
                ReservationNotificationType::ConfirmedToHost,
            ));
        } else {
            $this->messageBus->dispatch(new SendReservationNotification(
                (string) $reservation->getId(),
                ReservationNotificationType::PendingRequestToHost,
            ));
        }

        return $reservation;
    }
}
