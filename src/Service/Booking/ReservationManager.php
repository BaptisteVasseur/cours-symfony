<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Exception\BookingException;
use Doctrine\ORM\EntityManagerInterface;

final class ReservationManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PropertyAvailabilityService $propertyAvailabilityService,
        private BookingPricingService $bookingPricingService,
    ) {
    }

    public function create(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guestsCount,
    ): Reservation {
        if ($property->getHost()?->getId() === $guest->getId()) {
            throw new BookingException('Vous ne pouvez pas réserver votre propre logement.');
        }

        $this->propertyAvailabilityService->assertBookable($property, $checkin, $checkout, $guestsCount);
        $quote = $this->bookingPricingService->calculate($property, $checkin, $checkout);

        $reservation = new Reservation();
        $reservation->setProperty($property);
        $reservation->setGuest($guest);
        $reservation->setCheckinDate($checkin);
        $reservation->setCheckoutDate($checkout);
        $reservation->setGuestsCount($guestsCount);
        $reservation->setStatus($property->isInstantBooking() ? 'confirmed' : 'pending');
        $reservation->setTotalPrice(number_format($quote->total, 2, '.', ''));
        $reservation->setCleaningFee($quote->cleaningFee > 0 ? number_format($quote->cleaningFee, 2, '.', '') : null);
        $reservation->setServiceFee(number_format($quote->serviceFee, 2, '.', ''));
        $reservation->setSecurityDeposit($quote->securityDeposit !== null ? number_format($quote->securityDeposit, 2, '.', '') : null);
        $reservation->setCurrency($quote->currency);

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus(null);
        $history->setNewStatus($reservation->getStatus());
        $history->setChangedBy($guest);

        $this->entityManager->persist($reservation);
        $this->entityManager->persist($history);
        $this->entityManager->flush();

        return $reservation;
    }
}
