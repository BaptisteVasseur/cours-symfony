<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Exception\UnavailableDatesException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class BookingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AvailabilityChecker $availabilityChecker,
    ) {
    }

    public function book(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
    ): Reservation {
        return $this->em->wrapInTransaction(function () use ($property, $guest, $checkin, $checkout, $guests): Reservation {
            // Verrou : deux réservations simultanées sur CE logement se mettent en file d'attente
            $this->em->lock($property, LockMode::PESSIMISTIC_WRITE);

            // Re-vérification de la dispo À L'INTÉRIEUR du verrou
            if (!$this->availabilityChecker->isAvailable($property, $checkin, $checkout, $guests)) {
                throw new UnavailableDatesException('Ces dates ne sont plus disponibles.');
            }

            // Calcul du prix
            $nights = (int) $checkin->diff($checkout)->days;
            $pricePerNight = (float) $property->getPricePerNight();
            $cleaningFee = (float) ($property->getCleaningFee() ?? '0');
            $total = $pricePerNight * $nights + $cleaningFee;

            // Réservation instantanée => confirmée directement, sinon en attente de l'hôte
            $status = $property->isInstantBooking() ? 'confirmed' : 'pending';

            $reservation = (new Reservation())
                ->setProperty($property)
                ->setGuest($guest)
                ->setCheckinDate($checkin)
                ->setCheckoutDate($checkout)
                ->setGuestsCount($guests)
                ->setStatus($status)
                ->setTotalPrice(number_format($total, 2, '.', ''))
                ->setCleaningFee(number_format($cleaningFee, 2, '.', ''))
                ->setCurrency('EUR');

            // Traçabilité : on journalise le statut de départ
            $history = (new ReservationStatusHistory())
                ->setReservation($reservation)
                ->setOldStatus(null)
                ->setNewStatus($status)
                ->setChangedBy($guest)
                ->setCreatedAt(new \DateTimeImmutable());
            $reservation->addStatusHistory($history);

            $this->em->persist($reservation);
            $this->em->persist($history);

            // wrapInTransaction() fait le flush + commit automatiquement à la fin
            return $reservation;
        });
    }
}