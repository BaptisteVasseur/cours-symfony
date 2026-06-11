<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Service\Notification\ReservationEmailSender;
use Doctrine\DBAL\Exception\ConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class BookingManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AvailabilityChecker $availabilityChecker,
        private BookingPriceCalculator $priceCalculator,
        private ReservationEmailSender $emailSender,
    ) {
    }

    public function book(Property $property, User $guest, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout, int $guests): Reservation
    {
        try {
            $reservation = $this->entityManager->wrapInTransaction(function () use ($property, $guest, $checkin, $checkout, $guests): Reservation {
                $this->availabilityChecker->assertBookable($property, $checkin, $checkout, $guests);
                $price = $this->priceCalculator->calculate($property, $checkin, $checkout);
                $status = $property->isInstantBooking() ? 'confirmed' : 'pending';

                $reservation = new Reservation();
                $reservation->setProperty($property);
                $reservation->setGuest($guest);
                $reservation->setCheckinDate($checkin);
                $reservation->setCheckoutDate($checkout);
                $reservation->setGuestsCount($guests);
                $reservation->setStatus($status);
                $reservation->setTotalPrice($this->formatMoney($price->totalAmount));
                $reservation->setCleaningFee($this->formatNullableMoney($price->cleaningFee));
                $reservation->setServiceFee($this->formatNullableMoney($price->serviceFee));
                $reservation->setSecurityDeposit($this->formatNullableMoney($price->securityDeposit));
                $reservation->setCurrency($price->currency);

                $history = new ReservationStatusHistory();
                $history->setReservation($reservation);
                $history->setOldStatus(null);
                $history->setNewStatus($status);
                $history->setChangedBy($guest);

                $this->entityManager->persist($reservation);
                $this->entityManager->persist($history);
                $this->entityManager->flush();

                return $reservation;
            });

            $this->emailSender->sendReservationCreated($reservation);

            return $reservation;
        } catch (ConstraintViolationException $exception) {
            if ($exception->getSQLState() !== '23P01') {
                throw $exception;
            }

            throw new \DomainException('Ces dates viennent d’être réservées. Choisissez une autre période.', previous: $exception);
        }
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function formatNullableMoney(float $amount): ?string
    {
        return $amount > 0 ? $this->formatMoney($amount) : null;
    }
}
