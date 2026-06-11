<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;

final class ReservationAvailabilityService
{
    public const MAX_PENDING_OVERLAPS = 3;
    public const MIN_DURATION_HOURS = 3;
    public const GAP_HOURS = 3;

    public function __construct(private readonly ReservationRepository $reservationRepository)
    {
    }

    /**
     * Validate dates and check all availability constraints.
     * Returns a list of error messages, or empty array when the slot is available.
     *
     * @return list<string>
     */
    public function getAvailabilityErrors(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        ?Reservation $exclude = null,
        ?User $guest = null,
    ): array {
        $errors = [];

        if ($checkin >= $checkout) {
            $errors[] = 'La date de départ doit être postérieure à la date d\'arrivée.';

            return $errors;
        }

        $durationHours = ($checkout->getTimestamp() - $checkin->getTimestamp()) / 3600;
        if ($durationHours < self::MIN_DURATION_HOURS) {
            $errors[] = sprintf('La durée minimale de réservation est de %d heures.', self::MIN_DURATION_HOURS);
        }

        if ($checkin < new \DateTimeImmutable('now')) {
            $errors[] = 'La date d\'arrivée ne peut pas être dans le passé.';
        }

        if ($this->reservationRepository->countConfirmedConflicts($property, $checkin, $checkout, $exclude) > 0) {
            $errors[] = 'Ces dates ne sont pas disponibles : un séjour confirmé chevauche cette période (délai de 3h requis entre deux séjours).';
        }

        if (
            $this->reservationRepository->countPendingOverlaps($property, $checkin, $checkout, $exclude)
            >= self::MAX_PENDING_OVERLAPS
        ) {
            $errors[] = sprintf(
                'Ce créneau est très demandé : le maximum de %d demandes en attente simultanées est atteint.',
                self::MAX_PENDING_OVERLAPS,
            );
        }

        if (
            $guest !== null
            && $this->reservationRepository->countGuestOverlaps($guest, $checkin, $checkout, $exclude) > 0
        ) {
            $errors[] = 'Vous avez déjà une réservation (confirmée ou en attente) sur cette période.';
        }

        return $errors;
    }

    public function isAvailable(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        ?Reservation $exclude = null,
    ): bool {
        return $this->getAvailabilityErrors($property, $checkin, $checkout, $exclude) === [];
    }

    /**
     * @return array{nights: int, nightlyRate: float, subtotal: float, cleaningFee: float, serviceFee: float, totalPrice: float}
     */
    public function calculatePrice(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): array
    {
        $nights = (int) $checkin->diff($checkout)->days;
        if ($nights === 0) {
            $nights = (int) ceil(($checkout->getTimestamp() - $checkin->getTimestamp()) / 86400);
        }

        $nightlyRate = (float) $property->getPricePerNight();
        $subtotal = $nightlyRate * $nights;
        $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
        $serviceFee = round($subtotal * 0.12, 2);
        $totalPrice = round($subtotal + $cleaningFee + $serviceFee, 2);

        return [
            'nights' => $nights,
            'nightlyRate' => $nightlyRate,
            'subtotal' => $subtotal,
            'cleaningFee' => $cleaningFee,
            'serviceFee' => $serviceFee,
            'totalPrice' => $totalPrice,
        ];
    }
}
