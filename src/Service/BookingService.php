<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Repository\PropertyBlockedPeriodRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier du tunnel de réservation côté voyageur : calcul des nuits
 * indisponibles, validation d'une plage de dates (algorithme A.2), devis et
 * expiration du verrou de paiement de 15 minutes.
 *
 * Une nuit N correspond à l'occupation du logement de N@checkinTime (15h par
 * défaut) à N+1@checkoutTime (11h par défaut) : un blocage hôte « 12/06 17:00
 * → 13/06 11:00 » rend la nuit du 12 indisponible mais pas celle du 13.
 */
final class BookingService
{
    public const SERVICE_FEE_RATE = 0.12;

    private const DEFAULT_CHECKIN_HOUR = 15;
    private const DEFAULT_CHECKOUT_HOUR = 11;

    public function __construct(
        private readonly HostCalendar $hostCalendar,
        private readonly PropertyBlockedPeriodRepository $blockedPeriodRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Nuits non réservables sur [from, to), au format Y-m-d, en deux requêtes
     * pour toute la fenêtre (pas de requête par jour).
     *
     * @return list<string>
     */
    public function unavailableNights(Property $property, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $from = $from->setTime(0, 0);
        $to = $to->setTime(0, 0);

        $nights = [];

        foreach ($this->reservationRepository->findOverlappingForProperty($property, $from, $to) as $reservation) {
            if (!$this->hostCalendar->isBlocking($reservation)) {
                continue;
            }
            $start = max($reservation->getCheckinDate(), $from);
            $end = min($reservation->getCheckoutDate(), $to);
            for ($night = $start; $night < $end; $night = $night->modify('+1 day')) {
                $nights[$night->format('Y-m-d')] = true;
            }
        }

        foreach ($this->blockedPeriodRepository->findOverlapping($property, $from, $to->modify('+1 day')) as $period) {
            for ($night = $from; $night < $to; $night = $night->modify('+1 day')) {
                if (
                    $period->getStartAt() < $this->nightEnd($property, $night)
                    && $period->getEndAt() > $this->nightStart($property, $night)
                ) {
                    $nights[$night->format('Y-m-d')] = true;
                }
            }
        }

        $list = array_keys($nights);
        sort($list);

        return $list;
    }

    /**
     * Algorithme de disponibilité (A.2) sur les nuits [checkin, checkout).
     * Retourne null si la plage est réservable, sinon le motif du refus.
     */
    public function checkRangeAvailability(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guestsCount,
    ): ?string {
        if ($property->getStatus() !== 'published') {
            return 'Ce logement n\'est pas publié.';
        }

        if ($guestsCount > $property->getMaxGuests()) {
            return sprintf('Ce logement accepte au maximum %d voyageurs.', $property->getMaxGuests());
        }

        if ($this->hostCalendar->findBlockingReservations($property, $checkin, $checkout) !== []) {
            return 'Une autre réservation occupe déjà ces dates.';
        }

        $stayStart = $this->nightStart($property, $checkin);
        $stayEnd = $this->nightEnd($property, $checkout->modify('-1 day'));
        foreach ($this->blockedPeriodRepository->findOverlapping($property, $stayStart, $stayEnd) as $period) {
            return 'L\'hôte a déclaré une indisponibilité sur ces dates.';
        }

        return null;
    }

    /**
     * Devis du séjour : nuits × prix + frais de ménage + frais de service.
     *
     * @return array{nights: int, subtotal: float, cleaningFee: float, serviceFee: float, total: float}
     */
    public function quote(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): array
    {
        $nights = (int) $checkin->diff($checkout)->days;
        $subtotal = round((float) $property->getPricePerNight() * $nights, 2);
        $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
        $serviceFee = round($subtotal * self::SERVICE_FEE_RATE, 2);

        return [
            'nights' => $nights,
            'subtotal' => $subtotal,
            'cleaningFee' => $cleaningFee,
            'serviceFee' => $serviceFee,
            'total' => round($subtotal + $cleaningFee + $serviceFee, 2),
        ];
    }

    /**
     * Date limite de paiement d'une demande "pending" sous verrou de
     * 15 minutes (réservation instantanée uniquement), null sinon.
     */
    public function paymentDeadline(Reservation $reservation): ?\DateTimeImmutable
    {
        if ($reservation->getStatus() !== 'pending' || !$reservation->getProperty()->isInstantBooking()) {
            return null;
        }

        return $reservation->getCreatedAt()?->modify(sprintf('+%d minutes', HostCalendar::PENDING_LOCK_MINUTES));
    }

    /**
     * Annule une demande "pending" dont le verrou de paiement de 15 minutes a
     * expiré (logement en réservation instantanée uniquement — sur demande,
     * la décision revient à l'hôte). Retourne true si la réservation vient
     * d'être annulée.
     */
    public function expireStalePending(Reservation $reservation): bool
    {
        $deadline = $this->paymentDeadline($reservation);
        if ($deadline === null || $deadline > new \DateTimeImmutable()) {
            return false;
        }

        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason(sprintf(
            'Annulation automatique : paiement non effectué dans le délai de %d minutes.',
            HostCalendar::PENDING_LOCK_MINUTES,
        ));
        $this->entityManager->flush();

        return true;
    }

    private function nightStart(Property $property, \DateTimeImmutable $night): \DateTimeImmutable
    {
        $time = $property->getCheckinTime();

        return $night->setTime(
            $time !== null ? (int) $time->format('G') : self::DEFAULT_CHECKIN_HOUR,
            $time !== null ? (int) $time->format('i') : 0,
        );
    }

    private function nightEnd(Property $property, \DateTimeImmutable $night): \DateTimeImmutable
    {
        $time = $property->getCheckoutTime();

        return $night->modify('+1 day')->setTime(
            $time !== null ? (int) $time->format('G') : self::DEFAULT_CHECKOUT_HOUR,
            $time !== null ? (int) $time->format('i') : 0,
        );
    }
}
