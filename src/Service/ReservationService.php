<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ReservationService
{
    public function __construct(
        private readonly AvailabilityCheckerService $availabilityChecker,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function create(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guestsCount,
        string $currency = 'EUR',
    ): Reservation {
        $violations = $this->availabilityChecker->getViolations($property, $checkin, $checkout);

        if (count($violations) > 0) {
            throw new \DomainException(implode(' ', $violations));
        }

        $nights = (int) $checkin->diff($checkout)->days;
        $pricePerNight = (float) $property->getPricePerNight();
        $cleaningFee = $property->getCleaningFee() !== null ? (float) $property->getCleaningFee() : 0.0;
        $totalPrice = round($nights * $pricePerNight + $cleaningFee, 2);

        $reservation = new Reservation();
        $reservation->setProperty($property);
        $reservation->setGuest($guest);
        $reservation->setCheckinDate($checkin);
        $reservation->setCheckoutDate($checkout);
        $reservation->setGuestsCount($guestsCount);
        $reservation->setCurrency($currency);
        $reservation->setTotalPrice((string) $totalPrice);
        $reservation->setCleaningFee($property->getCleaningFee());
        $reservation->setSecurityDeposit($property->getSecurityDeposit());

        $status = $property->isInstantBooking() ? 'confirmed' : 'pending';
        $reservation->setStatus($status);

        $this->em->persist($reservation);
        $this->em->flush();

        return $reservation;
    }
}
